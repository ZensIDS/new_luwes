<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Voucher;
use App\Services\PriceCalculator;
use App\Support\OutletAccess;
use Illuminate\Http\Request;

class CashierPrintController extends Controller
{
    public function products(Request $request, PriceCalculator $calculator)
    {
        $outletId = OutletAccess::id($request, false);
        $selectedOutlet = $outletId ? Outlet::find($outletId) : null;
        $selectedIds = collect((array) $request->input('product_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $selectAll = $request->boolean('select_all');
        $excludedIds = collect((array) $request->input('excluded_product_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $printing = $request->boolean('print')
            && ($selectAll || $selectedIds->isNotEmpty());

        $query = Product::query()
            ->whereNotNull('code')
            ->where('code', '!=', '');
        if ($printing) {
            if ($selectAll) {
                $this->applyProductIndexFilters($query, $request, $outletId);
                if ($excludedIds->isNotEmpty()) {
                    $query->whereNotIn('id', $excludedIds);
                }
            } else {
                $query->whereIn('id', $selectedIds);
            }
        } else {
            $query->when($request->filled('search'), function ($productQuery) use ($request) {
                $term = trim((string) $request->input('search'));
                $productQuery->where(function ($searchQuery) use ($term) {
                    $searchQuery->where('name', 'like', "%{$term}%")
                        ->orWhere('code', 'like', "%{$term}%");
                });
            });
        }

        $relations = [
            'outletPrices' => function ($priceQuery) use ($outletId) {
                if ($outletId) {
                    $priceQuery->where('outlet_id', $outletId)->currentlyActive();
                } else {
                    $priceQuery->whereRaw('1 = 0');
                }
            },
        ];
        if ($outletId) {
            $relations['ownerStocks'] = fn ($stockQuery) => $stockQuery
                ->where('owner_id', $outletId)
                ->where('qty', '>', 0)
                ->where(function ($expiryQuery) {
                    $expiryQuery->whereNull('expired_at')->orWhere('expired_at', '>=', today()->toDateString());
                })
                ->orderBy('created_at');
        }

        if (! ($printing && $selectAll)) {
            $query->limit($printing ? 1000 : 100);
        }

        $products = $query->with($relations)
            ->orderBy('name')
            ->get();

        $products->each(function (Product $product) use ($calculator, $request) {
            $rule = $product->relationLoaded('outletPrices') ? $product->outletPrices->first() : null;
            $price = $calculator->calculateItem(
                app(\App\Services\LatestHpp::class)->fromLoaded(
                    $product->relationLoaded('ownerStocks') ? $product->ownerStocks : null,
                    $product->harga_beli
                ),
                $rule,
                $product
            );
            // Harga coret khusus label: HPP asli (tanpa pajak/diskon apa pun) + margin yang dihitung
            // dari HPP asli tersebut. Beda dengan master harga jual (margin dari harga dasar setelah diskon).
            $hppAsli = $calculator->money($price['hpp']);
            if ($rule) {
                $marginLabel = strtolower(trim((string) $rule->margin_type)) === 'percentage'
                    ? $calculator->money($hppAsli * ((float) $rule->margin_value / 100))
                    : $calculator->money($rule->margin_value);
            } else {
                $marginLabel = $price['margin_amount'];
            }
            $priceStrike = $calculator->money($hppAsli + $marginLabel);
            $product->setAttribute('print_price_hpp', $hppAsli);
            $product->setAttribute('print_price_margin', $marginLabel);
            $product->setAttribute('print_price_strike', $priceStrike);
            $product->setAttribute('print_price_net', $price['price']);
            // Persen diskon label = diskon yang diatur di Master Harga Jual (outlet-prices):
            // Disc Reg + Disc 1-4 + Disc Toko, dihitung bertingkat (20% + 5% = 24%), bukan dihitung dari selisih harga coret vs harga jual,
            // karena harga jual sudah terkena margin & pembulatan sehingga persennya bisa meleset.
            $discountPercent = $rule ? $this->labelDiscountPercent($price) : 0;
            $discountPercent = max(0, min(99, $discountPercent));
            $hasDiscount = $discountPercent >= 1;
            $product->setAttribute('print_has_discount', $hasDiscount);
            $product->setAttribute('print_discount_percent', $hasDiscount ? $discountPercent : 0);
            $product->setAttribute('print_qty', max(1, min(100, (int) $this->quantityFor($product->id, $request))));
        });

        // Urutan cetak label: produk berpromo (harga coret + persen diskon) dulu,
        // lalu yang tidak berpromo. Di dalam tiap grup tetap A-Z nama produk.
        $products = $products->sort(function (Product $a, Product $b) {
            $promoA = $a->getAttribute('print_has_discount') ? 0 : 1;
            $promoB = $b->getAttribute('print_has_discount') ? 0 : 1;

            return $promoA <=> $promoB ?: strcasecmp((string) $a->name, (string) $b->name);
        })->values();

        return view('cashier.print-products', [
            'products' => $products,
            'printItems' => $printing ? $products : collect(),
            'outlets' => OutletAccess::outlets(),
            'outletId' => $outletId,
            'selectedOutlet' => $selectedOutlet,
            'search' => $request->input('search'),
            'printing' => $printing,
            // Mode cetak label: thermal (hitam putih) atau warna.
            'mode' => in_array($request->input('mode'), ['thermal', 'warna'], true) ? $request->input('mode') : 'thermal',
            // Tanggal saat user menekan tombol cetak label (request POST print=1).
            'printedAt' => $printing ? now()->timezone('Asia/Jakarta') : null,
        ]);
    }

    /**
     * Persen diskon efektif dari aturan outlet-prices, dihitung BERTINGKAT seperti PriceCalculator:
     * Disc Reg -> Disc 1 -> Disc 2 -> Disc 3 -> Disc 4 -> Disc Toko, masing-masing dipotong dari
     * harga yang sudah terkena diskon sebelumnya. Contoh 20% + 5% = 1 - (0,80 x 0,95) = 24%.
     * Diskon nominal dikonversi ke persen terhadap harga dasar tempat diskon itu diterapkan.
     */
    private function labelDiscountPercent(array $price): int
    {
        $bases = [
            'disc_brand' => (float) $price['hpp_setelah_pajak'],
            'disc_tambahan' => (float) $price['harga_akhir'],
        ];
        // Disc Tambahan 2-4 bertingkat: basis = harga setelah diskon sebelumnya.
        $running = max(0, (float) $price['harga_akhir'] - (float) $price['disc_tambahan_amount']);
        foreach ([2, 3, 4] as $n) {
            $bases["disc_tambahan_{$n}"] = $running;
            $running = max(0, $running - (float) $price["disc_tambahan_{$n}_amount"]);
        }
        $bases['disc_toko'] = (float) $price['harga_aktif'];

        $remaining = 1.0; // bagian harga yang tersisa setelah semua diskon
        foreach ($bases as $key => $base) {
            $value = (float) ($price["{$key}_value"] ?? 0);
            if ($value <= 0) {
                continue;
            }
            $type = strtolower(trim((string) ($price["{$key}_type"] ?? '')));
            $fraction = $type === 'percentage'
                ? min(100, $value) / 100
                : ($base > 0 ? min(1, (float) ($price["{$key}_amount"] ?? 0) / $base) : 0);
            $remaining *= (1 - $fraction);
        }

        return (int) round((1 - $remaining) * 100);
    }

    private function applyProductIndexFilters($query, Request $request, ?int $outletId): void
    {
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            if ($search !== '') {
                $query->where(function ($searchQuery) use ($search, $outletId) {
                    $searchQuery->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('code', 'LIKE', "%{$search}%")
                        ->orWhere('harga_jual', 'LIKE', "%{$search}%")
                        ->orWhere('brand', 'LIKE', "%{$search}%")
                        ->orWhere('model', 'LIKE', "%{$search}%");

                    if ($outletId) {
                        $searchQuery->orWhereHas('ownerStocks', function ($stockQuery) use ($outletId, $search) {
                            $stockQuery->where('owner_id', $outletId)
                                ->where('qty', '>', 0)
                                ->where(function ($expiryQuery) {
                                    $expiryQuery->whereNull('expired_at')->orWhere('expired_at', '>=', today()->toDateString());
                                })
                                ->whereHas('stock', fn ($stock) => $stock->where('serial_number', 'LIKE', "%{$search}%"));
                        });
                    } else {
                        $searchQuery->orWhereHas('stocks', function ($stockQuery) use ($search) {
                            $stockQuery->where('serial_number', 'LIKE', "%{$search}%")
                                ->orWhere('status', 'LIKE', "%{$search}%");
                        });
                    }
                });
            }
        }

        if ($outletId) {
            $query->whereHas('ownerStocks', function ($stockQuery) use ($outletId) {
                $stockQuery->where('owner_id', $outletId)
                    ->where('qty', '>', 0)
                    ->where(function ($expiryQuery) {
                        $expiryQuery->whereNull('expired_at')->orWhere('expired_at', '>=', today()->toDateString());
                    });
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('lokasi')) {
            $query->where('lokasi', $request->input('lokasi'));
        }

        $statusFilter = $request->input('status_produk', 'sudah');
        if ($statusFilter !== 'all') {
            $query->where('status_produk', $statusFilter);
        }
    }

    public function vouchers(Request $request, PriceCalculator $calculator)
    {
        $outletId = OutletAccess::id($request, false);
        $selectedVoucherIds = collect((array) $request->input('voucher_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $selectedPromotionIds = collect((array) $request->input('promotion_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $printing = $request->boolean('print')
            && ($selectedVoucherIds->isNotEmpty() || $selectedPromotionIds->isNotEmpty());

        $query = Voucher::with(['product', 'products', 'outlets'])
            ->whereNotNull('code')
            ->where('code', '!=', '');
        if ($printing) {
            $query->whereIn('id', $selectedVoucherIds);
        } else {
            $query->whereDoesntHave('redemptions')
                ->where(function ($dateQuery) {
                    $dateQuery->whereNull('start_at')->orWhere('start_at', '<=', now());
                })
                ->where(function ($dateQuery) {
                    $dateQuery->whereNull('end_at')->orWhere('end_at', '>=', now());
                });
        }
        if (! $printing && $request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function ($searchQuery) use ($term) {
                $searchQuery->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            });
        }

        $vouchers = $query->orderBy('code')->limit($printing ? 1000 : 200)->get()
            ->filter(fn (Voucher $voucher) => $voucher->appliesToOutlet($outletId))
            ->values();

        $promotionQuery = Promotion::with(['products', 'outlets'])
            ->whereNotNull('code')
            ->where('code', '!=', '');
        if ($printing) {
            $promotionQuery
                ->whereIn('id', $selectedPromotionIds)
                ->where('type', '!=', 'flash_sale');
        } else {
            $promotionQuery
                ->where('is_active', true)
                ->where(function ($dateQuery) {
                    $dateQuery->whereNull('start_at')->orWhere('start_at', '<=', now());
                })
                ->where(function ($dateQuery) {
                    $dateQuery->whereNull('end_at')->orWhere('end_at', '>=', now());
                })
                ->where(function ($quotaQuery) {
                    $quotaQuery->whereNull('quota_qty')->orWhereColumn('used_qty', '<', 'quota_qty');
                });
        }
        if (! $printing && $request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $promotionQuery->where(function ($searchQuery) use ($term) {
                $searchQuery->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            });
        }

        $promotions = $promotionQuery->orderBy('code')->limit($printing ? 1000 : 200)->get()
            ->filter(fn (Promotion $promotion) => $this->promotionAppliesToOutlet($promotion, $outletId))
            ->values();

        $productIds = $vouchers->flatMap(function (Voucher $voucher) {
            return collect([$voucher->product_id])->merge($voucher->products->pluck('id'));
        })->merge($promotions->flatMap(fn (Promotion $promotion) => $promotion->products->pluck('id')))
            ->filter()->unique()->values();
        $pricingProducts = Product::query()
            ->whereIn('id', $productIds)
            ->with([
                'outletPrices' => function ($priceQuery) use ($outletId) {
                    if ($outletId) {
                        $priceQuery->where('outlet_id', $outletId)->currentlyActive();
                    } else {
                        $priceQuery->whereRaw('1 = 0');
                    }
                },
                'ownerStocks' => function ($stockQuery) use ($outletId) {
                    $stockQuery->when($outletId, fn ($query) => $query->where('owner_id', $outletId))
                        ->where('qty', '>', 0)
                        ->orderBy('created_at');
                },
            ])
            ->get()
            ->keyBy('id');

        $vouchers->each(function (Voucher $voucher) use ($pricingProducts, $calculator, $request) {
            $product = $voucher->product ?: $voucher->products->first();
            $priceProduct = $product ? $pricingProducts->get($product->id) : null;
            $discountAmount = null;
            if ($voucher->jenis === 'satuan' && $priceProduct) {
                $price = $calculator->calculateItem(
                    app(\App\Services\LatestHpp::class)->fromLoaded($priceProduct->ownerStocks, $priceProduct->harga_beli),
                    $priceProduct->outletPrices->first(),
                    $priceProduct
                );
                $discountAmount = $calculator->voucherAmount($voucher, $price['price']);
            }
            $voucher->setAttribute('print_product_name', $product?->name);
            $voucher->setAttribute('print_discount_amount', $discountAmount);
            $voucher->setAttribute('print_type', 'voucher');
            $voucher->setAttribute('print_qty', max(1, min(100, (int) $this->quantityFor($voucher->id, $request, 'qty'))));
        });

        $promotions->each(function (Promotion $promotion) use ($pricingProducts, $calculator, $request) {
            $product = $promotion->products->first();
            $priceProduct = $product ? $pricingProducts->get($product->id) : null;
            $discountAmount = null;

            // Bundles show no product discount. Flash-sale labels can still
            // show the nominal value calculated from their selected product.
            if ($promotion->type !== 'bundle' && $priceProduct) {
                $price = $calculator->calculateItem(
                    app(\App\Services\LatestHpp::class)->fromLoaded($priceProduct->ownerStocks, $priceProduct->harga_beli),
                    $priceProduct->outletPrices->first(),
                    $priceProduct
                );
                $discountAmount = $calculator->discountAmount(
                    $price['price'],
                    $promotion->discount_type,
                    (float) $promotion->discount_value
                );
            }

            $promotion->setAttribute('print_product_name', $product?->name);
            $promotion->setAttribute('print_discount_amount', $discountAmount);
            $promotion->setAttribute('print_type', 'promotion');
            $promotion->setAttribute('print_qty', max(1, min(100, (int) $this->quantityFor($promotion->id, $request, 'qty'))));
        });

        $printItems = $vouchers->concat($promotions)->sortBy('code')->values();

        return view('cashier.print-vouchers', [
            'vouchers' => $vouchers,
            'promotions' => $promotions,
            'printItems' => $printing ? $printItems : collect(),
            'outlets' => OutletAccess::outlets(),
            'outletId' => $outletId,
            'search' => $request->input('search'),
            'printing' => $printing,
        ]);
    }

    private function promotionAppliesToOutlet(Promotion $promotion, ?int $outletId): bool
    {
        if (! $outletId) {
            return true;
        }

        if ($promotion->outlet_id && (int) $promotion->outlet_id === $outletId) {
            return true;
        }

        return $promotion->outlet_id === null
            && ($promotion->outlets->isEmpty() || $promotion->outlets->contains('id', $outletId));
    }

    private function quantityFor(int $id, Request $request, string $key = 'qty'): int
    {
        $values = (array) $request->input($key, []);

        return (int) ($values[$id] ?? 1);
    }
}