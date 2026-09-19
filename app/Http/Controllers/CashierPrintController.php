<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Voucher;
use App\Services\PriceCalculator;
use App\Support\OutletAccess;
use Illuminate\Http\Request;

class CashierPrintController extends Controller
{
    public function products(Request $request, PriceCalculator $calculator)
    {
        $outletId = OutletAccess::id($request, false);
        $selectedIds = collect((array) $request->input('product_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $printing = $request->boolean('print') && $selectedIds->isNotEmpty();

        $query = Product::query()
            ->whereNotNull('code')
            ->where('code', '!=', '');
        if ($printing) {
            $query->whereIn('id', $selectedIds);
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
                    $expiryQuery->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
                })
                ->orderBy('created_at');
        }

        $products = $query->with($relations)
            ->orderBy('name')
            ->limit($printing ? 1000 : 100)
            ->get();

        $products->each(function (Product $product) use ($calculator, $request) {
            $rule = $product->relationLoaded('outletPrices') ? $product->outletPrices->first() : null;
            $stock = $product->relationLoaded('ownerStocks') ? $product->ownerStocks->first() : null;
            $price = $calculator->calculateItem(
                (float) ($stock?->hpp ?? $product->harga_beli ?? 0),
                $rule,
                $product
            );
            $product->setAttribute('print_price_active', $price['harga_aktif']);
            $product->setAttribute('print_price_net', $price['price']);
            $product->setAttribute('print_price_tax', $price['hpp_setelah_pajak']);
            $product->setAttribute('print_qty', max(1, min(100, (int) $this->quantityFor($product->id, $request))));
        });

        return view('cashier.print-products', [
            'products' => $products,
            'printItems' => $printing ? $products : collect(),
            'outlets' => OutletAccess::outlets(),
            'outletId' => $outletId,
            'search' => $request->input('search'),
            'printing' => $printing,
        ]);
    }

    public function vouchers(Request $request, PriceCalculator $calculator)
    {
        $outletId = OutletAccess::id($request, false);
        $selectedIds = collect((array) $request->input('voucher_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $printing = $request->boolean('print') && $selectedIds->isNotEmpty();

        $query = Voucher::with(['product', 'products', 'outlets'])
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->whereDoesntHave('redemptions')
            ->where(function ($dateQuery) {
                $dateQuery->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($dateQuery) {
                $dateQuery->whereNull('end_at')->orWhere('end_at', '>=', now());
            });
        if ($printing) {
            $query->whereIn('id', $selectedIds);
        } elseif ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function ($searchQuery) use ($term) {
                $searchQuery->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            });
        }

        $vouchers = $query->orderBy('code')->limit($printing ? 1000 : 200)->get()
            ->filter(fn (Voucher $voucher) => $voucher->appliesToOutlet($outletId))
            ->values();

        $productIds = $vouchers->flatMap(function (Voucher $voucher) {
            return collect([$voucher->product_id])->merge($voucher->products->pluck('id'));
        })->filter()->unique()->values();
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
                $stock = $priceProduct->ownerStocks->first();
                $price = $calculator->calculateItem(
                    (float) ($stock?->hpp ?? $priceProduct->harga_beli ?? 0),
                    $priceProduct->outletPrices->first(),
                    $priceProduct
                );
                $discountAmount = $calculator->voucherAmount($voucher, $price['price']);
            }
            $voucher->setAttribute('print_product_name', $product?->name);
            $voucher->setAttribute('print_discount_amount', $discountAmount);
            $voucher->setAttribute('print_qty', max(1, min(100, (int) $this->quantityFor($voucher->id, $request, 'qty'))));
        });

        return view('cashier.print-vouchers', [
            'vouchers' => $vouchers,
            'printItems' => $printing ? $vouchers : collect(),
            'outlets' => OutletAccess::outlets(),
            'outletId' => $outletId,
            'search' => $request->input('search'),
            'printing' => $printing,
        ]);
    }

    private function quantityFor(int $id, Request $request, string $key = 'qty'): int
    {
        $values = (array) $request->input($key, []);

        return (int) ($values[$id] ?? 1);
    }
}
