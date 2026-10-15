<?php

namespace App\Providers;

use App\Services\DeficitStockService;
use App\Services\ExpiredStockService;
use App\Services\LowStockService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
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
            // Badge Stok Minus (penjualan melebihi stok) untuk admin outlet / owner / superadmin.
            // Di-cache 60 detik per outlet agar tidak menghitung ulang di setiap halaman.
            if (Auth::check() && in_array(Auth::user()->role, ['staff-outlet', 'owner', 'superadmin'], true)) {
                $isStaff = Auth::user()->role === 'staff-outlet';
                $deficitOutletId = $isStaff ? (int) Auth::user()->outlet_id : null;

                $view->with('deficitCount', $isStaff && ! $deficitOutletId
                    ? 0
                    : Cache::remember('deficit-count:'.($deficitOutletId ?: 'all'), 60, fn () => app(DeficitStockService::class)->countOpen($deficitOutletId)));

                // Badge Stok Expired (sudah expired / akan expired). Ringkasan di-cache 60 detik di service.
                $view->with('expirySummary', $isStaff && ! $deficitOutletId
                    ? ['days' => 0, 'expired' => 0, 'soon' => 0, 'combo' => 0]
                    : app(ExpiredStockService::class)->summary($deficitOutletId));
            }

            // Notifikasi stok minimum hanya untuk admin-gudang (keputusan #1).
            // Stok realtime; peta min stok (hasil 3 bulan + adjustment) di-cache pendek. Hanya saat render halaman penuh.
            if (Auth::check() && Auth::user()->role === 'admin-gudang') {
                $lowStock = app(LowStockService::class)->summary(20);

                $view->with('lowStockCount', $lowStock['count']);
                $view->with('lowStockProducts', $lowStock['products']);
            }
        });
    }
}