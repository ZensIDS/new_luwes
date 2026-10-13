<?php

namespace App\Http\Requests;

/**
 * Pendaftaran produk baru dari form master harga outlet.
 *
 * - Aturan dasar sama dengan menu Product (ProductRequest), tetapi error-nya disimpan di
 *   error bag terpisah ("newProduct") agar tidak bercampur dengan error form aturan harga.
 * - Harga beli boleh diisi per SATUAN BESAR (kebiasaan nota supplier). Nilai yang disimpan
 *   ke products.harga_beli tetap per SATUAN KECIL: harga besar / isi konversi.
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
            'harga_beli' => 'required|numeric|min:0',
            'harga_beli_per' => 'required|in:besar,kecil',
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
     * Data siap simpan ke tabel products.
     * Bila harga beli dimasukkan per satuan besar, HPP otomatis dibagi isi konversi
     * sehingga products.harga_beli selalu per satuan kecil.
     */
    public function productData(): array
    {
        $data = $this->validated();

        $per = $data['harga_beli_per'] ?? 'kecil';
        unset($data['harga_beli_per']);

        if ($per === 'besar') {
            $data['harga_beli'] = round(((float) $data['harga_beli']) / (float) $data['konversi_qty'], 2);
        }

        $data['min_stock'] = (int) ($data['min_stock'] ?? 0);

        return $data;
    }
}