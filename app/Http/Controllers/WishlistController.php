<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index()
    {
        $wish_list = app('wishlist');
        $total = $wish_list->getTotal();

        $wish_list->getContent()->each(function ($item) use (&$items) {
            $items[] = $item;
        });

        return view('market.wishlist', [
            'items' => $items,
            'total' => $total,
        ]);
    }

    public function store(Request $request)
    {
        $wish_list = app('wishlist');

        $wish_list->add($request->id, $request->name, $request->price, $request->quantity, []);

        return redirect()->route('wishlist.index')->with('toast_success', 'Product is Added to Wishlist Successfully !');
    }

    public function moveToCart()
    {
        $wish_list = app('wishlist');
        $cart = app('cart');

        $cart->session(auth()->id());

        $wish_list->getContent()->each(function ($item) use ($cart) {
            $product = Product::find($item->id);
            $cart->add([
                'id' => $item->id,
                'name' => $item->name,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'attributes' => $item->attributes,
                'conditions' => $item->conditions,
                'associatedModel' => $product,
            ]);
        });

        // Clear the wishlist
        $wish_list->clear();

        return redirect()->route('wishlist.index')->with('toast_success', 'Products is Added to Cart Successfully !');
    }

    public function remove(Request $request)
    {
        $wish_list = app('wishlist');

        $wish_list->remove($request->id);

        return redirect()->route('wishlist.index')->with('toast_success', 'Product is Remove to Wishlist Successfully !');
    }
}
