<?php

namespace App\Providers;

use App\Services\LowStockService;
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
            // Notifikasi stok minimum hanya untuk admin-gudang (keputusan #1).
            // Dihitung di SQL & realtime (tanpa cache), dan hanya saat render halaman penuh.
            if (Auth::check() && Auth::user()->role === 'admin-gudang') {
                $lowStock = app(LowStockService::class)->summary(20);

                $view->with('lowStockCount', $lowStock['count']);
                $view->with('lowStockProducts', $lowStock['products']);
            }
        });
    }
}