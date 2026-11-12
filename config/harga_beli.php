<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mode pagar harga beli / HPP
    |--------------------------------------------------------------------------
    | Harga beli (products.harga_beli, products.harga_beli_besar) dan HPP batch
    | outlet (owner_stocks.hpp) hanya boleh berubah lewat jalur resmi yang
    | dibungkus HargaBeliGuard::allow().
    |
    |   log     : perubahan di luar jalur resmi hanya DICATAT ke log (aman, tidak error)
    |   enforce : perubahan di luar jalur resmi DITOLAK (HargaBeliGuardException)
    |
    | Default "enforce" (Tahap 6). Untuk kembali ke mode catat saja, isi
    | HARGA_BELI_GUARD_MODE=log di .env (lalu php artisan config:clear).
    */
    'mode' => env('HARGA_BELI_GUARD_MODE', 'enforce'),

    // Channel log untuk pelanggaran (null = channel default aplikasi).
    'log_channel' => env('HARGA_BELI_GUARD_LOG_CHANNEL'),

    /*
    |--------------------------------------------------------------------------
    | Seeder demo di production
    |--------------------------------------------------------------------------
    | DemoDataSeeder / OutletReportSeeder menulis harga beli dan HPP data demo.
    | Di environment production keduanya DITOLAK, kecuali nilai ini true.
    */
    'allow_demo_seeder' => env('HARGA_BELI_ALLOW_DEMO_SEEDER', false),

];