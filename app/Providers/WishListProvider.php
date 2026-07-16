<?php

namespace App\Providers;

use App\Models\DatabaseStorage;
use Darryldecode\Cart\Cart;
use Illuminate\Support\ServiceProvider;

class WishListProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        $this->app->singleton('wishlist', function ($app) {
            $storage = new DatabaseStorage();
            $events = $app['events'];
            $instanceName = 'cart_2';
            $session_key = '88uuiioo99888';

            return new Cart(
                $storage,
                $events,
                $instanceName,
                $session_key,
                config('shopping_cart')
            );
        });
    }
}
