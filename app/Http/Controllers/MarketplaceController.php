<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\Penjualan;
use App\Models\PenjualanItem;
use App\Models\Product;
use App\Models\Slider;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\Voucher;
use Carbon\Carbon;
use Darryldecode\Cart\CartCondition;
use Darryldecode\Cart\Facades\CartFacade as Cart;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MarketplaceController extends Controller
{
    public function index(Request $request, $category = null)
    {
        $bestSellingProducts = Product::with('penjualanItems')->take(10)->get()->sortByDesc(function ($product) {
            return $product->penjualanItems->sum('qty');
        });
        $topRatedProducts = Product::withAvg('reviews', 'rating')->orderBy('reviews_avg_rating', 'desc')->take(10)->get();

        $products = Product::query();
        if ($request->search) {
            $products = $products->where('name', 'LIKE', "%{$request->search}%")
                ->orWhere('code', 'LIKE', "%{$request->search}%")
                ->orWhere('harga_jual', 'LIKE', "%{$request->search}%");
        }
        if ($category) {
            $products->whereHas('category', function ($query) use ($category) {
                $query->whereRaw("REPLACE(name, ' ', '_') = ?", [$category]);
            });
        }

        return view('market.index', [
            'products' => $products->get(),
            'bestSellingProducts' => $bestSellingProducts,
            'topRatedProducts' => $topRatedProducts,
            'sliders' => Slider::where('status', 'active')->get(),
        ]);
    }

    public function show($id)
    {
        $product = Product::find($id);
        $fiveStarReviews = $product->reviews->where('rating', 5)->count();
        $fourStarReviews = $product->reviews->where('rating', 4)->count();
        $threeStarReviews = $product->reviews->where('rating', 3)->count();
        $twoStarReviews = $product->reviews->where('rating', 2)->count();
        $oneStarReviews = $product->reviews->where('rating', 1)->count();

        return view('market.show', [
            'product' => $product,
            'fiveStarReviews' => $fiveStarReviews,
            'fourStarReviews' => $fourStarReviews,
            'threeStarReviews' => $threeStarReviews,
            'twoStarReviews' => $twoStarReviews,
            'oneStarReviews' => $oneStarReviews,
        ]);
    }

    public function cities(Request $request)
    {
        $apiKey = env('APP_KEY_RAJAONGKIR');
        $provinceId = $request->query('province_id');

        $response = Http::withoutVerifying()->withHeaders([
            'key' => $apiKey
        ])->get("https://api.rajaongkir.com/starter/city?province=$provinceId");

        return response()->json(json_decode($response->body(), true)['rajaongkir']['results']);
    }

    public function checkout(Request $request)
    {
        $apiKey = env('APP_KEY_RAJAONGKIR');
        $cartItems = Cart::session(auth()->id())->getContent();

        if ($request->isMethod('POST')) {
            if ($cartItems->isEmpty()) {
                return redirect()->back()->withErrors(['cart' => 'Your cart is empty.']);
            }
            // Set the origin city ID here
            $origin = json_decode(Storage::disk('public')->get('settings.json'), true)['origin'];
            $courier = $request->courier;
            $destination = $request->city;
            $weight = 0;
            foreach ($cartItems as $item) {
                $product = $item->associatedModel;
                $weight += $product->berat * $item->quantity;
            }

            $response = Http::withoutVerifying()->withHeaders([
                'key' => $apiKey
            ])->get("https://api.rajaongkir.com/starter/city?id=$destination");
            $destinationName = json_decode($response->body(), true)['rajaongkir']['results']['city_name'];

            $response = Http::withoutVerifying()->asForm()->withHeaders([
                'key' => $apiKey
            ])->post('https://api.rajaongkir.com/starter/cost', [
                'origin' => $origin,
                'destination' => $destination,
                'weight' => $weight,
                'courier' => $courier
            ]);

            $costs = json_decode($response->body(), true)['rajaongkir']['results'][0]['costs'];
            $shipping_cost = $costs[0]['cost'][0]['value'];

            $condition = new CartCondition([
                'name' => 'Shipping',
                'type' => 'shipping',
                'target' => 'total',
                'value' => "+$shipping_cost",
                'order' => 1
            ]);

            Cart::session(auth()->id())->condition($condition);

            return redirect()->back()->with([
                'shipping_cost' => $shipping_cost,
                'destination' => $destinationName,
                'courier' => $courier,
                'weight' => $weight
            ]);
        }

        $response = Http::withoutVerifying()->withHeaders([
            'key' => $apiKey
        ])->get('https://api.rajaongkir.com/starter/province');
        $provinces = json_decode($response->body(), true)['rajaongkir']['results'];

        return view('market.checkout', [
            'payments' => PaymentMethod::get(),
            'cartItems' => Cart::session(auth()->id())->getContent(),
            'provinces' => $provinces,
        ]);
    }

    public function store(Request $request)
    {
        $cartItems = Cart::session(auth()->id())->getContent();
        $cartSubtotal = Cart::session(auth()->id())->getSubTotal();
        $cartTotal = Cart::session(auth()->id())->getTotal();

        if ($cartItems->isEmpty()) {
            return redirect()->back()->withErrors(['cart' => 'Your cart is empty.']);
        }

        $shippingConditions = Cart::session(auth()->id())->getConditionsByType('shipping');
        if ($shippingConditions->isEmpty()) {
            return redirect()->back()->withErrors(['shipping' => 'Please select a shipping method.']);
        }

        DB::beginTransaction();
        try {
            $lastOrder = Penjualan::whereNull('outlet_id')->orderBy('created_at', 'desc')->first();
            $nextInvoiceNumber = $lastOrder ? ((int) substr($lastOrder->code, 3) + 1) : 1;
            $nextInvoiceNumber = str_pad($nextInvoiceNumber, 3, '0', STR_PAD_LEFT);
            $nextInvoiceCode = 'INV'.$nextInvoiceNumber;

            $penjualan = new Penjualan([
                'code' => $nextInvoiceCode,
                'customer_id' => auth()->user()->id,
                'outlet_id' => null,
                'kasir_id' => null,
                'kas_id' => null,
                'discount' => $cartSubtotal - $cartTotal,
                'total' => $cartTotal,
            ]);
            $penjualan->save();
            foreach ($cartItems as $item) {
                $now = Carbon::now();
                $stocks = Stock::where('product_id', $item->id)
                    // ->where('created_at', '<=', $now)
                    // ->where('expired_at', '>=', $now)
                    // ->orderBy('expired_at', 'asc')
                    ->lockForUpdate()
                    ->get();
                if ($stocks->isEmpty()) {
                    throw new Exception('Stock not found or expired for product: '.$item->name);
                }
                $remainingQty = $item->quantity;
                foreach ($stocks as $stock) {
                    if ($remainingQty <= 0) {
                        break;
                    }
                    if ($stock->qty >= $remainingQty) {
                        $stock->qty -= $remainingQty;
                        $remainingQty = 0;
                    } else {
                        $remainingQty -= $stock->qty;
                        $stock->qty = 0;
                    }
                    $stock->save();
                }
                if ($remainingQty > 0) {
                    throw new Exception('Insufficient stock quantity for product: '.$item->name);
                }
                $penjualanItem = new PenjualanItem([
                    'penjualan_id' => $penjualan->id,
                    'product_id' => $item->id,
                    'qty' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->getPriceSum(),
                ]);
                $penjualanItem->save();
            }

            Transaction::create([
                'penjualan_id' => $penjualan->id,
                'payment_method' => $request->payment_method,
                'tanggal' => now(),
            ]);

            Cart::session(auth()->id())->clear();
            Cart::session(auth()->id())->clearCartConditions();
            Cart::session(auth()->id())->removeConditionsByType('subtotal');

            DB::commit();

            return redirect()->route('marketcart.index')->with('success', 'Voucher applied successfully!');
        } catch (Exception $e) {
            DB::rollBack();

            // return $e->getMessage();
            return redirect()->route('marketcart.index')->with('errors', $e->getMessage());
        }
    }

    public function coupon(Request $request)
    {
        $voucher = Voucher::where('code', $request->code)->first();
        $voucherConditions = Cart::session(auth()->id())->getConditionsByType('Voucher');

        foreach ($voucherConditions as $condition) {
            Cart::session(auth()->id())->removeCartCondition($condition->getName());
        }

        if ($voucher) {
            $condition = new CartCondition([
                'name' => $voucher->name,
                'type' => 'Voucher',
                'target' => $voucher->jenis == 'keseluruhan' ? 'total' : 'subtotal',
                'value' => $voucher->type == 'percentage' ? -$voucher->value.'%' : -$voucher->value,
            ]);
            if ($voucher->jenis == 'satuan') {
                $productId = $voucher->product_id;
                Cart::session(auth()->id())->addItemCondition($productId, $condition);
            } else {
                Cart::session(auth()->id())->condition($condition);
            }

            return redirect()->back()->with('success', 'Voucher applied successfully!');
        } else {
            return redirect()->back()->with('error', 'Invalid voucher code');
        }
    }
}
