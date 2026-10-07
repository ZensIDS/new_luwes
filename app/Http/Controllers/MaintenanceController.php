<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;

/**
 * Perintah perawatan yang dipanggil lewat URL (hosting tanpa akses terminal).
 * Route-nya hanya boleh dibuka superadmin (lihat routes/web.php).
 */
class MaintenanceController extends Controller
{
    public function optimizeClear()
    {
        Artisan::call('optimize:clear');

        return redirect('/login')->with(['success' => 'Optimization Berhasil']);
    }

    public function storageLink()
    {
        Artisan::call('storage:link');

        return redirect('/login')->with(['success' => 'Optimization Berhasil']);
    }
}