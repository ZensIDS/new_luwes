<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Exception;
use Illuminate\Http\Request;

class CartUserController extends Controller
{
    public function index()
    {
        return view('market.cart.index', [
            'cartItems' => Cart::session(auth()->id())->getContent(),
        ]);
    }

    public function addToCart(Request $request)
    {
        $product = Product::find($request->id);
        $cartItem = Cart::session(auth()->id())->get($product->id);
        $cartQuantity = $cartItem ? $cartItem->quantity : 0;
        if (
            $product->stocks()
            // ->where('created_at', '<=', now())
            // ->where('expired_at', '>=', now())
                ->sum('qty') >= $request->quantity + $cartQuantity
        ) {
            try {
                Cart::session(auth()->id())->add([
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->harga_jual,
                    'quantity' => $request->quantity,
                    'attributes' => [],
                    'associatedModel' => $product,
                ]);

                return redirect()->route('marketcart.index')->with('toast_success', 'Product is Added to Cart Successfully !');
            } catch (Exception $e) {
                return redirect()->back()->with('toast_error', 'An error occurred: '.$e->getMessage());
            }
        } else {
            return redirect()->back()->with('toast_error', 'Stock tidak mencukupi');
        }
    }

    public function updateCart(Request $request)
    {
        $product = Product::find($request->id);
        if (
            $product->stocks()
            // ->where('created_at', '<=', now())
            // ->where('expired_at', '>=', now())
                ->sum('qty') >= $request->quantity
        ) {
            Cart::session(auth()->id())->update($request->id, [
                'quantity' => [
                    'relative' => false,
                    'value' => $request->quantity,
                ],
            ]);

            return redirect()->route('marketcart.index')->with('toast_success', 'Item Cart is Updated Successfully !');
        } else {
            return redirect()->back()->with('toast_error', 'Stock tidak mencukupi');
        }
    }


    public function removeCart(Request $request)
    {
        Cart::session(auth()->id())->remove($request->id);
        Cart::session(auth()->id())->clearCartConditions();

        return redirect()->route('marketcart.index')->with('toast_success', 'Item Cart Remove Successfully !');
    }

    public function clearAllCart()
    {
        Cart::session(auth()->id())->clear();
        Cart::session(auth()->id())->clearCartConditions();

        return redirect()->route('marketcart.index')->with('toast_success', 'All Item Cart Clear Successfully !');
    }
}
