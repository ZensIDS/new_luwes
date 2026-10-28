<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Sertifikat & tanda tangan untuk QZ Tray (cetak struk langsung).
 * Dengan request yang ditandatangani + sertifikat dipercaya di PC kasir,
 * opsi "Remember this decision" di popup QZ bisa dipilih.
 *
 * File dibuat sekali (lihat panduan): storage/app/qz/private-key.pem dan storage/app/qz/digital-certificate.txt
 */
class QzSignController extends Controller
{
    public function certificate()
    {
        $path = storage_path('app/qz/digital-certificate.txt');
        abort_unless(is_file($path), 404);

        return response(file_get_contents($path), 200, ['Content-Type' => 'text/plain']);
    }

    public function sign(Request $request)
    {
        $path = storage_path('app/qz/private-key.pem');
        abort_unless(is_file($path), 404);

        $key = openssl_pkey_get_private(file_get_contents($path));
        abort_unless($key, 500, 'Private key QZ tidak valid.');

        $signature = '';
        openssl_sign((string) $request->input('request'), $signature, $key, OPENSSL_ALGO_SHA512);

        return response(base64_encode($signature), 200, ['Content-Type' => 'text/plain']);
    }
}