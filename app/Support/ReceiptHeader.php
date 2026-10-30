<?php

namespace App\Support;

/**
 * Header struk (bagian paling atas) yang statis dan dipakai sama oleh
 * struk penjualan, struk BON, dan struk tutup kasir.
 */
class ReceiptHeader
{
    public const STORE = 'LUWES PACITAN';

    public const LINES = [
        'Jl. Panglima Sudirman 11',
        'PACITAN',
        'NPWP : 20.685.943.1-647.000',
        'Tanggal Pengukuhan : 1 Agustus 2024',
    ];
}