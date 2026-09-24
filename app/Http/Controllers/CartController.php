<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CartController extends Controller
{
    public function index(Request $request)
    {
        // Get the cart data
        $cart = $request->user()->cart()->get();

        // Add the available stock for each product
        foreach ($cart as $item) {
            $now = Carbon::now();
            $stockQty = $item->stocks()
                ->available()
                ->sum('qty');
            $item->availableStock = $stockQty;

            // Get available serial numbers for serialized products
            if ($item->is_serialized) {
                $item->availableSerials = $item->stocks()
                    ->available()
                    ->whereNotNull('serial_number')
                    ->pluck('serial_number', 'id')
                    ->toArray();
            }
        }
        // Return the cart data with the available stocks
        return response($cart);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'barcode' => 'required|exists:products,code',
                'serial_number' => 'nullable|string'
            ]);

            $barcode = $request->barcode;
            $product = Product::where('code', $barcode)->first();
            $now = Carbon::now();

            if ($product->is_serialized && $request->serial_number) {
                // For serialized products, check specific serial number
                $stock = $product->stocks()
                    ->available()
                    ->where('serial_number', $request->serial_number)
                    ->first();

                if (! $stock) {
                    return response(['message' => 'Serial number not available'], 400);
                }

                // Check if this serial is already in cart
                $cart = $request->user()->cart()
                    ->where('code', $barcode)
                    ->wherePivot('serial_number', $request->serial_number)
                    ->first();

                if ($cart) {
                    return response(['message' => 'Serial number already in cart'], 400);
                }

                $request->user()->cart()->attach($product->id, [
                    'qty' => 1,
                    'serial_number' => $request->serial_number,
                    'stock_id' => $stock->id
                ]);
            } else {
                // For non-serialized products
                $stockQty = $product->stocks()
                    ->available()
                    ->sum('qty');

                $cart = $request->user()->cart()->where('code', $barcode)->first();
                if ($cart) {
                    if ($stockQty <= $cart->pivot->qty) {
                        return response(['message' => 'Product available only: '.$stockQty], 400);
                    }
                    $cart->pivot->qty = $cart->pivot->qty + 1;
                    $cart->pivot->save();
                } else {
                    if ($stockQty < 1) {
                        return response(['message' => 'Product out of stock'], 400);
                    }
                    $request->user()->cart()->attach($product->id, ['qty' => 1]);
                }
            }

            return response('success', 204);
        } catch (Exception $e) {
            error_log($e->getMessage());

            return response(['message' => 'An error occurred while processing your request.'], 500);
        }
    }

    public function changeQty(Request $request)
    {
        try {
            $request->validate([
                'product_id' => 'required|exists:products,id',
                'qty' => 'required|integer|min:1',
                'serial_number' => 'nullable|string'
            ]);

            $product = Product::find($request->product_id);

            if ($product->is_serialized) {
                // For serialized products, quantity should always be 1
                return response(['message' => 'Cannot change quantity for serialized products'], 400);
            }

            $cart = $request->user()->cart()->where('products.id', $request->product_id)->first();

            if ($cart) {
                // $now = Carbon::now();
                $stockQty = $product->stocks()
                    ->available()
                    ->sum('qty');

                if ($stockQty < $request->qty) {
                    return response(['message' => 'Product available only: '.$stockQty], 400);
                } else {
                    $cart->pivot->qty = $request->qty;
                    $cart->pivot->save();
                }
            }

            return response(['success' => true]);
        } catch (Exception $e) {
            error_log($e->getMessage());

            return response(['message' => 'An error occurred while processing your request.'], 500);
        }
    }

    public function removeSerial(Request $request)
    {
        try {
            $request->validate([
                'product_id' => 'required|exists:products,id',
                'serial_number' => 'required|string'
            ]);

            $request->user()->cart()
                ->wherePivot('product_id', $request->product_id)
                ->wherePivot('serial_number', $request->serial_number)
                ->detach();

            return response(['success' => true]);
        } catch (Exception $e) {
            error_log($e->getMessage());

            return response(['message' => 'An error occurred while processing your request.'], 500);
        }
    }

    public function addToWishlist(Request $request)
    {
        $request->validate([
            'cart' => 'required|array',
            'cart.*.id' => 'required|exists:products,id',
            'cart.*.pivot.qty' => 'required|integer|min:1',
            'cart.*.pivot.stock_id' => 'nullable|exists:stocks,id',
            'outlet_id' => 'required',
            'customer_id' => 'required',
            'name' => 'required',
        ]);

        foreach ($request->cart as $item) {
            $product = Product::find($item['id']);
            $stockId = $item['pivot']['stock_id'] ?? null;

            $request->user()->wishlist()->attach($product->id, [
                'qty' => $item['pivot']['qty'],
                'outlet_id' => $request->outlet_id,
                'customer_id' => $request->customer_id,
                'name' => $request->name,
                'stock_id' => $stockId
            ]);

            // Update stock status if it's a specific stock item
            if ($stockId) {
                \App\Models\Stock::where('id', $stockId)
                    ->update(['status' => 'on_keep']);
            }
        }
        $request->user()->cart()->detach();

        return response(['success' => true]);
    }

    public function getWishlist(Request $request, $outlet_id)
    {
        $wishlist = $request->user()->wishlist()->wherePivot('outlet_id', $outlet_id)->withPivot('name', 'customer_id', 'outlet_id')->get();
        $grouped = $wishlist->groupBy(['pivot.name', 'pivot.customer_id']);

        return response($grouped);
    }

    public function moveToCart(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'customer_id' => 'required',
        ]);

        $wishlistItems = $request->user()->wishlist()
            ->wherePivot('name', $request->name)
            ->wherePivot('customer_id', $request->customer_id)
            ->withPivot('stock_id', 'qty')
            ->get();

        foreach ($wishlistItems as $item) {
            // For serialized products, detach using both product_id and stock_id
            if ($item->is_serialized && $item->pivot->stock_id) {
                $request->user()->wishlist()
                    ->wherePivot('product_id', $item->id)
                    ->wherePivot('stock_id', $item->pivot->stock_id)
                    ->detach();
            } else {
                // For non-serialized products, just detach by product_id
                $request->user()->wishlist()->detach($item->id);
            }

            // Rest of your existing cart attachment logic...
            $pivotData = [
                'qty' => $item->pivot->qty,
                'stock_id' => $item->pivot->stock_id
            ];

            if ($item->is_serialized && $item->pivot->stock_id) {
                $stock = \App\Models\Stock::find($item->pivot->stock_id);
                if ($stock && $stock->serial_number) {
                    $pivotData['serial_number'] = $stock->serial_number;
                }
            }

            $request->user()->cart()->attach($item->id, $pivotData);

            if ($item->pivot->stock_id) {
                \App\Models\Stock::where('id', $item->pivot->stock_id)
                    ->update(['status' => 'free']);
            }
        }

        return response(['success' => true]);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ]);
        $request->user()->cart()->detach($request->product_id);

        return response('success', 204);
    }

    public function empty(Request $request)
    {
        $request->user()->cart()->detach();

        return response('success', 204);
    }
}
