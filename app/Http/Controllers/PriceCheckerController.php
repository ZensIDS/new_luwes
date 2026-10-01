<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\OutletPrice;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Voucher;
use App\Services\LatestHpp;
use App\Services\PriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PriceCheckerController extends Controller
{
    public function index(Request $request)
    {
        $selectedOutlet = $this->resolveOutlet($request);

        return view('price-checker.index', [
            'selectedOutlet' => $selectedOutlet,
            'selectedOutletId' => $selectedOutlet?->id,
        ]);
    }

    public function lookup(Request $request, PriceCalculator $calculator)
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:100'],
            'outlet_id' => ['nullable', 'integer', 'exists:outlets,id'],
            'outlet' => ['nullable', 'string', 'max:100'],
        ]);

        $barcode = trim($validated['barcode']);
        $outlet = $this->resolveOutlet($request, false);
        $outletId = $outlet?->id;
        $product = Product::query()->where('code', $barcode)->first();

        if (! $product) {
            return response()->json([
                'message' => 'Produk tidak ditemukan. Silakan scan barcode yang lain.',
            ], 404);
        }

        $priceRule = $outletId
            ? OutletPrice::query()
                ->where('outlet_id', $outletId)
                ->where('product_id', $product->id)
                ->currentlyActive()
                ->first()
            : null;
        // HPP terbaru dipakai untuk semua batch, sama seperti master harga dan POS.
        $price = $calculator->calculateItem(
            app(LatestHpp::class)->forProduct((int) $outletId, (int) $product->id),
            $priceRule,
            $product
        );

        $promotions = $this->activePromotionsForProduct($product, $outletId)
            ->map(fn (Promotion $promotion) => $this->formatPromotion($promotion, (int) $price['price'], $calculator))
            ->values();
        $vouchers = $this->activeVouchersForProduct($product, $outletId)
            ->map(fn (Voucher $voucher) => $this->formatVoucher($voucher, (int) $price['price'], $calculator))
            ->values();

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name ?: 'Produk tanpa nama',
                'barcode' => $product->code,
                'unit' => $product->satuan,
            ],
            // Sama seperti halaman master harga barang:
            // Harga Coret = HPP setelah pajak + margin, Harga Jual POS = harga akhir.
            'price_strike' => (int) $calculator->money(
                $price['hpp_setelah_pajak'] + $price['margin_amount']
            ),
            'price' => (int) $price['price'],
            'promotions' => $promotions->concat($vouchers)->values(),
        ]);
    }

    private function resolveOutlet(Request $request, bool $fallbackToFirst = true): ?Outlet
    {
        if ($request->filled('outlet_id')) {
            return Outlet::query()->findOrFail($request->integer('outlet_id'));
        }

        $identifier = trim((string) $request->input('outlet', ''));
        if ($identifier !== '') {
            $identifierSlug = Str::slug($identifier);
            $outlet = Outlet::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->first(fn (Outlet $outlet) => Str::slug((string) $outlet->name) === $identifierSlug);

            abort_if(! $outlet, 404, 'Outlet tidak ditemukan.');

            return $outlet;
        }

        return $fallbackToFirst
            ? Outlet::query()->orderBy('name')->first()
            : null;
    }

    private function activePromotionsForProduct(Product $product, ?int $outletId)
    {
        $query = Promotion::query()
            ->with(['promotionProducts.product', 'bonuses', 'outlets'])
            ->whereHas('promotionProducts', fn ($productQuery) => $productQuery->where('product_id', $product->id))
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

        if ($outletId) {
            $query->where(function ($outletQuery) use ($outletId) {
                $outletQuery
                    ->where(function ($legacyQuery) use ($outletId) {
                        $legacyQuery->whereNull('outlet_id')->orWhere('outlet_id', $outletId);
                    })
                    ->whereDoesntHave('outlets')
                    ->orWhereHas('outlets', fn ($outletsQuery) => $outletsQuery->whereKey($outletId));
            });
        } else {
            // With no outlet selected, only expose promotions that are explicitly
            // global. The page normally selects the first configured outlet.
            $query->whereNull('outlet_id')->whereDoesntHave('outlets');
        }

        return $query
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
    }

    private function activeVouchersForProduct(Product $product, ?int $outletId)
    {
        return Voucher::query()
            ->with(['products', 'outlets'])
            ->withCount('redemptions')
            ->where(function ($productQuery) use ($product) {
                $productQuery
                    ->where('product_id', $product->id)
                    ->orWhereHas('products', fn ($productsQuery) => $productsQuery->whereKey($product->id))
                    ->orWhere(function ($globalQuery) {
                        $globalQuery->whereNull('product_id')->whereDoesntHave('products');
                    });
            })
            ->where(function ($dateQuery) {
                $dateQuery->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($dateQuery) {
                $dateQuery->whereNull('end_at')->orWhere('end_at', '>=', now());
            })
            ->orderBy('id')
            ->get()
            ->filter(function (Voucher $voucher) use ($outletId, $product) {
                $limitAvailable = $voucher->limit === null
                    || ((int) $voucher->limit > 0 && (int) $voucher->redemptions_count < (int) $voucher->limit);

                return $limitAvailable
                    && $voucher->appliesToOutlet($outletId)
                    && $voucher->appliesToProduct($product->id);
            })
            ->values();
    }

    private function formatPromotion(Promotion $promotion, int $basePrice, PriceCalculator $calculator): array
    {
        $type = strtolower(trim((string) $promotion->type));
        $discountType = strtolower(trim((string) $promotion->discount_type));
        $promoPrice = null;
        $discount = 0;
        $terms = [];
        $limits = [];

        if ($type === 'flash_sale') {
            if ($discountType === 'fixed_price') {
                $promoPrice = min($basePrice, $calculator->money($promotion->discount_value));
                $discount = max(0, $basePrice - $promoPrice);
                $summary = 'Harga spesial flash sale';
            } else {
                $discount = $calculator->discountAmount(
                    $basePrice,
                    $discountType,
                    (float) $promotion->discount_value
                );
                $promoPrice = max(0, $basePrice - $discount);
                $summary = $discountType === 'percentage'
                    ? 'Diskon '.$this->number($promotion->discount_value).'%'
                    : 'Hemat '.$this->rupiah($discount);
            }

            if ($promotion->max_qty !== null) {
                $limits[] = 'Maks. '.$this->number($promotion->max_qty).' unit berdiskon per transaksi';
            }
        } elseif ($type === 'bundle') {
            $requirements = $promotion->promotionProducts
                ->map(function ($target) {
                    $name = $target->product?->name ?: 'produk';

                    return $this->number($target->required_qty).'× '.$name;
                })
                ->implode(' + ');
            $summary = 'Promo bundle';
            if ($requirements !== '') {
                $terms[] = 'Beli paket: '.$requirements;
            }

            $bundleDiscount = $calculator->money($promotion->bundle_price);
            if ($bundleDiscount > 0) {
                $summary .= ' · Hemat '.$this->rupiah($bundleDiscount).' per paket';
            }

            if ($promotion->max_qty !== null) {
                $limits[] = 'Maks. '.$this->number($promotion->max_qty).' paket per transaksi';
            }
        } else {
            $summary = 'Promo tersedia';
        }

        if ($promotion->bonuses->isNotEmpty()) {
            $bonuses = $promotion->bonuses
                ->map(fn ($bonus) => 'Bonus '.$this->number($bonus->qty).'× '.($bonus->name ?: 'produk'))
                ->implode(', ');
            $summary .= ' · '.$bonuses;
        }

        if ((float) $promotion->min_purchase > 0) {
            $terms[] = 'Min. belanja '.$this->rupiah($promotion->min_purchase);
        }

        if ($promotion->desc && trim($promotion->desc) !== '') {
            $terms[] = trim($promotion->desc);
        }

        if ($promotion->quota_qty !== null) {
            $remaining = max(0, (int) $promotion->quota_qty - (int) $promotion->used_qty);
            $limits[] = 'Sisa kuota '.$this->number($remaining).' dari '.$this->number($promotion->quota_qty);
        }

        if (! $promotion->stackable) {
            $limits[] = 'Tidak dapat digabung dengan promo lain';
        }

        return [
            'name' => $promotion->name ?: 'Promo',
            'summary' => $summary,
            'terms' => $terms,
            'period' => $this->period($promotion->start_at, $promotion->end_at),
            'limits' => $limits,
            'price' => $promoPrice,
            'discount' => $discount,
            'type' => $type,
        ];
    }

    private function formatVoucher(Voucher $voucher, int $basePrice, PriceCalculator $calculator): array
    {
        $discount = (int) $calculator->voucherAmount($voucher, $basePrice);
        $discountType = strtolower(trim((string) $voucher->type));
        $summary = $discountType === 'percentage'
            ? 'Voucher diskon '.$this->number($voucher->value).'%'
            : 'Voucher hemat '.$this->rupiah($voucher->value);
        $terms = [];
        $limits = [];

        if ($voucher->min_purchase && (float) $voucher->min_purchase > 0) {
            $terms[] = 'Min. belanja '.$this->rupiah($voucher->min_purchase);
        }

        if ($voucher->desc && trim($voucher->desc) !== '') {
            $terms[] = trim($voucher->desc);
        }

        if ($voucher->max_discount_amount !== null) {
            $limits[] = 'Maks. potongan '.$this->rupiah($voucher->max_discount_amount);
        }

        if ($voucher->limit !== null) {
            $remaining = max(0, (int) $voucher->limit - (int) $voucher->redemptions_count);
            $limits[] = 'Sisa pemakaian '.$this->number($remaining).' dari '.$this->number($voucher->limit);
        }

        return [
            'name' => $voucher->name ?: 'Voucher',
            'summary' => $summary,
            'terms' => $terms,
            'period' => $this->period($voucher->start_at, $voucher->end_at),
            'limits' => $limits,
            'price' => $discount > 0 ? max(0, $basePrice - $discount) : null,
            'discount' => $discount,
            'type' => 'voucher',
        ];
    }

    private function period($start, $end): string
    {
        $format = fn ($date) => $date->copy()->locale('id')->translatedFormat('j M Y, H:i');

        if ($start && $end) {
            return $format($start).' – '.$format($end);
        }

        if ($end) {
            return 'Sampai '.$format($end);
        }

        if ($start) {
            return 'Mulai '.$format($start);
        }

        return 'Tanpa batas waktu';
    }

    private function rupiah(float|int|null $amount): string
    {
        return 'Rp '.number_format((float) ($amount ?? 0), 0, ',', '.');
    }

    private function number(float|int|null $amount): string
    {
        $amount = (float) ($amount ?? 0);

        return fmod($amount, 1) === 0.0
            ? number_format($amount, 0, ',', '.')
            : number_format($amount, 2, ',', '.');
    }
}