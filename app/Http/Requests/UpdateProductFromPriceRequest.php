<?php

namespace App\Http\Requests;

/**
 * Edit barang dari form master harga outlet (panel yang sama dengan "Daftarkan Produk Baru").
 *
 * - Aturan sama dengan NewProductFromPriceRequest; cek barcode unik otomatis mengecualikan produk
 *   yang sedang diedit (ProductRequest::rules() membaca {product} dari route).
 * - Error tetap di error bag "newProduct" (panel yang sama), sehingga tampilan error panel tidak berubah.
 * - Hanya kolom yang ada di panel yang boleh diubah (lihat PANEL_FIELDS), supaya kolom lain
 *   (harga_jual, diskon, berat, dll) tidak tertimpa null oleh prepareForValidation().
 */
class UpdateProductFromPriceRequest extends NewProductFromPriceRequest
{
    /** Kolom produk yang tampil di panel dan boleh diubah dari sini. */
    public const PANEL_FIELDS = [
        'code', 'name', 'category_id', 'brand', 'satuan', 'satuan_besar', 'konversi_qty',
        'harga_beli', 'harga_beli_besar', 'min_stock', 'lokasi', 'warna', 'ukuran', 'model',
        'status_produk', 'status_produk_note',
    ];

    public function productData(): array
    {
        $data = array_intersect_key(parent::productData(), array_flip(self::PANEL_FIELDS));

        // Kolom opsional yang dikosongkan user harus benar-benar dikosongkan (bukan dibiarkan data lama).
        foreach (['brand', 'satuan', 'satuan_besar', 'konversi_qty', 'lokasi', 'warna', 'ukuran', 'model'] as $field) {
            $data[$field] = $data[$field] ?? null;
        }

        return $data;
    }
}