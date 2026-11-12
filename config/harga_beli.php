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
    | Mulai dari "log". Pindah ke "enforce" hanya di akhir (Tahap 6) setelah
    | log bersih dari pelanggaran.
    */
    'mode' => env('HARGA_BELI_GUARD_MODE', 'log'),

    // Channel log untuk pelanggaran (null = channel default aplikasi).
    'log_channel' => env('HARGA_BELI_GUARD_LOG_CHANNEL'),

];