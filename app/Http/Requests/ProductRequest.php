<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\IndonesianNumber;

class ProductRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        $this->merge([
            // Barcode hasil scan kadang membawa spasi/enter di ujung; buang supaya cek unik akurat.
            'code' => $this->filled('code') ? trim((string) $this->input('code')) : $this->input('code'),
            // Nama selalu disimpan HURUF BESAR (juga dijaga di model Product::setNameAttribute).
            'name' => $this->filled('name') ? mb_strtoupper(trim((string) $this->input('name')), 'UTF-8') : $this->input('name'),
            // Harga beli boleh diinput per satuan kecil (pcs) atau per satuan besar (karton/box).
            'harga_beli_per' => $this->input('harga_beli_per') === 'besar' ? 'besar' : 'kecil',
            'status_produk' => $this->input('status_produk', 'sudah'),
            'status_produk_note' => $this->filled('status_produk_note')
                ? trim((string) $this->input('status_produk_note'))
                : null,
            'konversi_qty' => $this->filled('konversi_qty')
                ? round((float) $this->input('konversi_qty'), 2)
                : null,
            'harga_beli' => IndonesianNumber::parse($this->input('harga_beli')),
            'harga_jual' => IndonesianNumber::parse($this->input('harga_jual')),
            'diskon' => IndonesianNumber::parse($this->input('diskon')),
        ]);
    }

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $productId = $this->route('product')?->id;

        return [
            'pic' => 'nullable',
            'code' => [
                'required',
                Rule::unique('products', 'code')->ignore($productId),
            ],
            'name' => 'required',
            'category_id' => 'required',
            'desc' => 'nullable',
            'warna' => 'nullable',
            'ukuran' => 'nullable',
            'brand' => 'nullable|string',
            'model' => 'nullable',
            'is_serialized' => 'nullable',
            'harga_beli' => 'required|numeric|min:0',
            'harga_beli_per' => 'required|in:besar,kecil',
            'harga_jual' => 'nullable',
            'diskon' => 'nullable',
            'berat' => 'nullable',
            'satuan' => 'nullable|string',
            'satuan_besar' => ['nullable', 'string', 'max:255', 'required_if:harga_beli_per,besar'],
            'konversi_qty' => ['nullable', 'numeric', 'min:1', 'required_if:harga_beli_per,besar'],
            'min_stock' => 'required|integer|min:0',
            'lokasi' => 'nullable|string',
            'status_produk' => 'required|in:free_produk,tambahan_diskon,free_tester,listing,lunas,belum_lunas,sudah',
            'status_produk_note' => 'nullable|required_if:status_produk,tambahan_diskon|string|max:255',
            'supplier_ids' => 'nullable|array',
            'supplier_ids.*' => 'exists:suppliers,id',
        ];
    }

    /**
     * Data siap simpan ke tabel products.
     * Bila harga beli dimasukkan per satuan besar, HPP otomatis dibagi isi konversi
     * sehingga products.harga_beli selalu per satuan kecil (pcs).
     */
    public function productData(): array
    {
        $data = $this->validated();

        $per = $data['harga_beli_per'] ?? 'kecil';
        unset($data['harga_beli_per']);

        if ($per === 'besar') {
            $data['harga_beli'] = round(((float) $data['harga_beli']) / (float) $data['konversi_qty'], 2);
        }

        return $data;
    }

    /**
     * Custom validation messages.
     */
    public function messages()
    {
        return [
            'code.required' => 'Barcode wajib diisi.',
            'code.unique' => 'Barcode/Code ini sudah digunakan oleh produk lain.',
            'name.required' => 'Nama produk wajib diisi.',
            'category_id.required' => 'Kategori produk wajib dipilih.',
            'harga_beli.required' => 'Harga beli wajib diisi.',
            'harga_beli.numeric' => 'Harga beli harus berupa angka.',
            'harga_beli.min' => 'Harga beli tidak boleh kurang dari 0.',
            'harga_beli_per.in' => 'Pilihan harga beli per satuan tidak valid.',
            'satuan_besar.required_if' => 'Satuan besar wajib diisi jika harga beli dimasukkan per satuan besar.',
            'konversi_qty.required_if' => 'Isi konversi wajib diisi jika harga beli dimasukkan per satuan besar.',
            'konversi_qty.min' => 'Isi konversi minimal 1.',
            'harga_jual.required' => 'Harga jual wajib diisi.',
            'satuan.string' => 'Satuan harus berupa teks.',
            'min_stock.required' => 'Minimal stok wajib diisi.',
            'min_stock.integer' => 'Minimal stok harus berupa angka.',
            'min_stock.min' => 'Minimal stok tidak boleh kurang dari 0.',
            'lokasi.string' => 'Lokasi harus berupa teks.',
            'status_produk.required' => 'Status produk wajib dipilih.',
            'status_produk.in' => 'Status produk yang dipilih tidak valid.',
            'status_produk_note.required_if' => 'Tambahan diskon wajib diisi saat status produk adalah Tambahan diskon.',
            'status_produk_note.string' => 'Catatan status produk harus berupa teks.',
            'supplier_ids.array' => 'Supplier harus berupa array.',
            'supplier_ids.*.exists' => 'Supplier yang dipilih tidak valid.',
        ];
    }
}