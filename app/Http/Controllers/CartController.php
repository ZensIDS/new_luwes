<?php

namespace App\Http\Controllers;

use App\Models\OwnerStock;
use App\Models\OutletPrice;
use App\Models\Product;
use App\Services\PriceCalculator;
use App\Services\PromotionService;
use App\Support\OutletAccess;
use Exception;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request, PriceCalculator $calculator, PromotionService $promotionService)
    {
        $outletId = OutletAccess::id($request);
        $cart = $request->user()->cart()
            ->wherePivot('outlet_id', (string) $outletId)
            ->withPivot('qty', 'serial_number', 'stock_id', 'owner_stock_id', 'outlet_id')
            ->get();

        $allocations = [];
        foreach ($cart as $cartIndex => $item) {
            $ownerStocks = $item->ownerStocks()
                ->where('owner_id', $outletId)
                ->where('qty', '>', 0)
                ->where(function ($expiryQuery) {
                    $expiryQuery->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
                })
                ->with('stock')
                ->orderBy('created_at')
                ->get();
            $item->availableStock = $item->ownerStocks()
                ->where('owner_id', $outletId)
                ->where('qty', '>', 0)
                ->where(function ($expiryQuery) {
                    $expiryQuery->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
                })
                ->sum('qty');

            if ($item->is_serialized) {
                $item->availableSerials = $item->ownerStocks()
                    ->with('stock')
                    ->where('owner_id', $outletId)
                    ->where('qty', '>', 0)
                    ->where(function ($expiryQuery) {
                        $expiryQuery->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
                    })
                    ->whereHas('stock', fn ($query) => $query->whereNotNull('serial_number'))
                    ->get()
                    ->mapWithKeys(fn ($ownerStock) => [
                        $ownerStock->id => $ownerStock->stock?->serial_number,
                    ])
                    ->toArray();
            }
            $rule = OutletPrice::where('outlet_id', $outletId)
                ->where('product_id', $item->id)
                ->currentlyActive()
                ->first();
            $remainingQty = max(1, (int) $item->pivot->qty);
            $firstPrice = null;
            foreach ($ownerStocks as $ownerStock) {
                if ($remainingQty <= 0) {
                    break;
                }
                $price = $calculator->calculateItem(
                    (float) ($ownerStock->hpp ?? $item->harga_beli ?? 0),
                    $rule,
                    $item
                );
                $allocatedQty = $item->is_serialized ? 1 : min($remainingQty, (int) $ownerStock->qty);
                $firstPrice ??= $price;
                $allocations[] = [
                    'cart_index' => $cartIndex,
                    'product' => $item,
                    'ownerStock' => $ownerStock,
                    'qty' => $allocatedQty,
                    'price' => $price,
                    'base_line_total' => (int) ($price['price'] * $allocatedQty),
                    'line_total' => (int) ($price['price'] * $allocatedQty),
                ];
                $remainingQty -= $allocatedQty;
            }
        }

        $promotionCodes = collect($request->input('promotion_codes', []))
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $promotionResult = $promotionService->calculate($allocations, $outletId, null, false, $promotionCodes);
        $allocationsByCartItem = collect($promotionResult['allocations'])->groupBy('cart_index');
        foreach ($cart as $cartIndex => $item) {
            $itemAllocations = $allocationsByCartItem->get($cartIndex, collect());
            $baseSubtotal = (int) $itemAllocations->sum('base_line_total');
            $cashierSubtotal = (int) $itemAllocations->sum('line_total');
            $promotionDiscount = (int) $itemAllocations->sum('promotion_discount');
            $firstAllocation = $itemAllocations->first();
            $firstPrice = $firstAllocation['price'] ?? null;

            $item->cashierPrice = $firstPrice;
            $item->cashier_base_subtotal = $baseSubtotal;
            $item->cashier_unit_price = $calculator->money($baseSubtotal / max(1, (int) $item->pivot->qty));
            $item->cashier_subtotal = $cashierSubtotal;
            $item->cashier_promotion_discount = $promotionDiscount;
            $item->cashier_promotions = $itemAllocations
                ->flatMap(fn ($allocation) => collect($allocation['promotion_details'] ?? []))
                ->pluck('promotion_name')
                ->unique()
                ->values()
                ->all();
        }

        return response($cart);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'barcode' => 'required|exists:products,code',
                'serial_number' => 'nullable|string',
                'outlet_id' => 'required|integer|exists:outlets,id',
            ]);
            $outletId = OutletAccess::id($request);
            $product = Product::where('code', $request->barcode)->firstOrFail();

            if ($product->is_serialized) {
                $stock = OwnerStock::with('stock')
                    ->where('owner_id', $outletId)
                    ->where('product_id', $product->id)
                    ->where('qty', '>', 0)
                    ->where(function ($expiryQuery) {
                        $expiryQuery->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
                    })
                    ->when($request->serial_number, function ($query) use ($request) {
                        $query->whereHas('stock', fn ($stockQuery) => $stockQuery->where('serial_number', $request->serial_number));
                    })
                    ->first();

                if (! $stock) {
                    return response(['message' => 'Serial number tidak tersedia di outlet ini.'], 400);
                }

                $cart = $request->user()->cart()
                    ->wherePivot('outlet_id', (string) $outletId)
                    ->wherePivot('owner_stock_id', $stock->id)
                    ->first();

                if ($cart) {
                    return response(['message' => 'Serial number sudah ada di keranjang.'], 400);
                }

                $request->user()->cart()->attach($product->id, [
                    'qty' => 1,
                    'outlet_id' => $outletId,
                    'owner_stock_id' => $stock->id,
                    'serial_number' => $stock->stock?->serial_number,
                ]);
            } else {
                $stockQty = OwnerStock::where('owner_id', $outletId)
                    ->where('product_id', $product->id)
                    ->where('qty', '>', 0)
                    ->where(function ($expiryQuery) {
                        $expiryQuery->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
                    })
                    ->sum('qty');
                $cart = $request->user()->cart()
                    ->wherePivot('outlet_id', (string) $outletId)
                    ->where('products.id', $product->id)
                    ->first();

                if ($cart) {
                    if ($stockQty <= $cart->pivot->qty) {
                        return response(['message' => 'Stok outlet tersedia hanya: ' . $stockQty], 400);
                    }
                    $cart->pivot->qty++;
                    $cart->pivot->save();
                } else {
                    if ($stockQty < 1) {
                        return response(['message' => 'Produk tidak memiliki stok di outlet ini.'], 400);
                    }
                    $request->user()->cart()->attach($product->id, [
                        'qty' => 1,
                        'outlet_id' => $outletId,
                    ]);
                }
            }

            return response('success', 204);
        } catch (Exception $e) {
            report($e);

            return response(['message' => $e->getMessage()], 400);
        }
    }

    public function changeQty(Request $request)
    {
        try {
            $request->validate([
                'product_id' => 'required|exists:products,id',
                'qty' => 'required|integer|min:1',
                'outlet_id' => 'required|integer|exists:outlets,id',
            ]);
            $outletId = OutletAccess::id($request);
            $product = Product::findOrFail($request->product_id);

            if ($product->is_serialized) {
                return response(['message' => 'Quantity barang serialized selalu satu.'], 400);
            }

            $stockQty = OwnerStock::where('owner_id', $outletId)
                ->where('product_id', $product->id)
                ->where('qty', '>', 0)
                ->where(function ($expiryQuery) {
                    $expiryQuery->whereNull('expired_at')->orWhereDate('expired_at', '>=', today());
                })
                ->sum('qty');
            if ($stockQty < $request->qty) {
                return response(['message' => 'Stok outlet tersedia hanya: ' . $stockQty], 400);
            }

            $cart = $request->user()->cart()
                ->wherePivot('outlet_id', (string) $outletId)
                ->where('products.id', $request->product_id)
                ->first();
            if ($cart) {
                $cart->pivot->qty = $request->qty;
                $cart->pivot->save();
            }

            return response(['success' => true]);
        } catch (Exception $e) {
            report($e);

            return response(['message' => $e->getMessage()], 400);
        }
    }

    public function removeSerial(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'serial_number' => 'required|string',
            'outlet_id' => 'required|integer|exists:outlets,id',
        ]);
        $outletId = OutletAccess::id($request);
        $request->user()->cart()
            ->wherePivot('outlet_id', (string) $outletId)
            ->wherePivot('serial_number', $request->serial_number)
            ->detach($request->product_id);

        return response(['success' => true]);
    }

    public function addToWishlist(Request $request)
    {
        $request->validate([
            'cart' => 'required|array',
            'cart.*.id' => 'required|exists:products,id',
            'cart.*.pivot.qty' => 'required|integer|min:1',
            'cart.*.pivot.owner_stock_id' => 'nullable|exists:owner_stocks,id',
            'outlet_id' => 'required|integer|exists:outlets,id',
            'customer_id' => 'nullable',
            'name' => 'required',
        ]);
        $outletId = OutletAccess::id($request);

        foreach ($request->cart as $item) {
            $product = Product::findOrFail($item['id']);
            $ownerStockId = $item['pivot']['owner_stock_id'] ?? null;
            $request->user()->wishlist()->attach($product->id, [
                'qty' => $item['pivot']['qty'],
                'outlet_id' => $outletId,
                'customer_id' => $request->customer_id,
                'name' => $request->name,
                'owner_stock_id' => $ownerStockId,
            ]);
        }
        $request->user()->cart()->wherePivot('outlet_id', (string) $outletId)->detach();

        return response(['success' => true]);
    }

    public function getWishlist(Request $request, $outlet_id)
    {
        $request->merge(['outlet_id' => $outlet_id]);
        $outletId = OutletAccess::id($request);
        $wishlist = $request->user()->wishlist()
            ->wherePivot('outlet_id', (string) $outletId)
            ->withPivot('name', 'customer_id', 'outlet_id', 'owner_stock_id', 'qty')
            ->get();
        $grouped = $wishlist->groupBy(['pivot.name', 'pivot.customer_id']);

        return response($grouped);
    }

    public function moveToCart(Request $request)
    {
        $request->validate(['name' => 'required', 'customer_id' => 'nullable', 'outlet_id' => 'required']);
        $outletId = OutletAccess::id($request);
        $wishlistItems = $request->user()->wishlist()
            ->wherePivot('outlet_id', (string) $outletId)
            ->wherePivot('name', $request->name)
            ->wherePivot('customer_id', $request->customer_id)
            ->withPivot('owner_stock_id', 'qty')
            ->get();

        foreach ($wishlistItems as $item) {
            $request->user()->wishlist()
                ->wherePivot('product_id', $item->id)
                ->wherePivot('outlet_id', (string) $outletId)
                ->wherePivot('name', $request->name)
                ->detach();
            $request->user()->cart()->attach($item->id, [
                'qty' => $item->pivot->qty,
                'outlet_id' => $outletId,
                'owner_stock_id' => $item->pivot->owner_stock_id,
            ]);
        }

        return response(['success' => true]);
    }

    public function destroy(Request $request)
    {
        $request->validate(['product_id' => 'required|integer|exists:products,id', 'outlet_id' => 'required']);
        $outletId = OutletAccess::id($request);
        $request->user()->cart()
            ->wherePivot('outlet_id', (string) $outletId)
            ->detach($request->product_id);

        return response('success', 204);
    }

    public function empty(Request $request)
    {
        $outletId = OutletAccess::id($request);
        $request->user()->cart()->wherePivot('outlet_id', (string) $outletId)->detach();

        return response('success', 204);
    }
}
