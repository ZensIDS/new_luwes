<?php

namespace App\Http\Requests;

/**
 * Pendaftaran produk baru dari form master harga outlet.
 *
 * - Aturan dasar sama dengan menu Product (ProductRequest), tetapi error-nya disimpan di
 *   error bag terpisah ("newProduct") agar tidak bercampur dengan error form aturan harga.
 * - Harga beli diisi di salah satu kolom: SATUAN KECIL atau SATUAN BESAR (kebiasaan nota supplier).
 *   Pasangannya dihitung otomatis lewat isi konversi (lihat ProductRequest::productData()); products.harga_beli
 *   tetap per SATUAN KECIL dan jadi acuan hitung laba.
 * - Minimum stock tidak wajib (default 0).
 */
class NewProductFromPriceRequest extends ProductRequest
{
    protected $errorBag = 'newProduct';

    protected function prepareForValidation()
    {
        parent::prepareForValidation();

        $this->merge([
            'min_stock' => $this->filled('min_stock') ? $this->input('min_stock') : 0,
            'harga_beli_per' => $this->input('harga_beli_per') === 'besar' ? 'besar' : 'kecil',
        ]);
    }

    public function rules()
    {
        return array_merge(parent::rules(), [
            // harga_beli / harga_beli_besar / harga_beli_per memakai aturan dari ProductRequest.
            'satuan' => ['nullable', 'string', 'required_with:satuan_besar'],
            'satuan_besar' => ['nullable', 'string', 'max:255', 'required_if:harga_beli_per,besar', 'required_with:konversi_qty'],
            'konversi_qty' => ['nullable', 'numeric', 'min:1', 'required_if:harga_beli_per,besar', 'required_with:satuan_besar'],
            'min_stock' => 'nullable|integer|min:0',
        ]);
    }

    public function messages()
    {
        return array_merge(parent::messages(), [
            'harga_beli.numeric' => 'Harga beli harus berupa angka.',
            'harga_beli.min' => 'Harga beli tidak boleh kurang dari 0.',
            'harga_beli_per.in' => 'Pilihan harga beli per satuan tidak valid.',
            'satuan.required_with' => 'Satuan kecil wajib diisi jika satuan besar diisi.',
            'satuan_besar.required_if' => 'Satuan besar wajib diisi jika harga beli dimasukkan per satuan besar.',
            'satuan_besar.required_with' => 'Satuan besar wajib diisi jika isi konversi diisi.',
            'konversi_qty.required_if' => 'Isi konversi wajib diisi jika harga beli dimasukkan per satuan besar.',
            'konversi_qty.required_with' => 'Isi konversi wajib diisi jika satuan besar diisi.',
            'konversi_qty.min' => 'Isi konversi minimal 1.',
            'min_stock.integer' => 'Minimal stok harus berupa angka.',
        ]);
    }

    /**
     * Data siap simpan ke tabel products (harga kecil/besar dihitung di ProductRequest::productData()).
     */
    public function productData(): array
    {
        $data = parent::productData();
        $data['min_stock'] = (int) ($data['min_stock'] ?? 0);

        return $data;
    }
}