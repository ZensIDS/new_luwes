<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\ProductMinimumAdjustment;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        Paginator::useBootstrap();

        Blade::directive('currency', function ($expression) {
            return "Rp. <?php echo number_format($expression,0,',','.'); ?>";
        });

        View::composer('layouts.market.header', function ($view) {
            $view->with('categories', \App\Models\Category::get());
        });

        $loadCompanyLogo = function () {
            $settings = json_decode(Storage::disk('public')->get('settings.json') ?? '{}', true) ?? [];
            $logo = $settings['logo'] ?? null;

            return $logo ? Storage::url($logo) : asset('img/logo.png');
        };

        View::composer('layouts.guest', function ($view) use ($loadCompanyLogo) {
            $view->with('companyLogo', $loadCompanyLogo());
        });

        View::composer('layouts.master', function ($view) use ($loadCompanyLogo) {
            $view->with('companyLogo', $loadCompanyLogo());
            if (Auth::check()) {
                $today = now()->toDateString();
                $activeAdjs = ProductMinimumAdjustment::activeOn($today)
                    ->orderByDesc('active_from')
                    ->orderByDesc('id')
                    ->get()
                    ->keyBy('product_id');

                $lowStockCandidates = Product::query()
                    ->select('id', 'name', 'min_stock')
                    ->where(function ($query) use ($activeAdjs) {
                        $query->where('min_stock', '>', 0);

                        if ($activeAdjs->isNotEmpty()) {
                            $query->orWhereIn('id', $activeAdjs->keys());
                        }
                    })
                    ->withSum('stocks as available_stock_qty', 'qty_available')
                    ->get()
                    ->map(function ($product) use ($activeAdjs) {
                        $current = (int) ($product->available_stock_qty ?? 0);
                        $adj = $activeAdjs->get($product->id);
                        $product->effective_min_qty = $adj
                            ? (int) ceil($product->min_stock * (1 + $adj->adjustment_percentage / 100))
                            : (int) $product->min_stock;
                        $product->available_stock_qty = $current;

                        return $product;
                    });

                $lowStockProducts = $lowStockCandidates
                    ->filter(fn ($product) => $product->available_stock_qty <= $product->effective_min_qty)
                    ->sortBy('name')
                    ->values();

                $view->with('lowStockCount', $lowStockProducts->count());
                $view->with('lowStockProducts', $lowStockProducts->take(20));
            }
        });
    }
}
