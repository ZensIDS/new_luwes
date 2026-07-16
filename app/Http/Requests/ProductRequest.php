<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        $this->merge([
            'status_produk' => $this->input('status_produk', 'sudah'),
            'status_produk_note' => $this->filled('status_produk_note')
                ? trim((string) $this->input('status_produk_note'))
                : null,
            'konversi_qty' => $this->filled('konversi_qty')
                ? round((float) $this->input('konversi_qty'), 2)
                : null,
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
            // 'brand' => 'nullable',
            'model' => 'nullable',
            'is_serialized' => 'nullable',
            'harga_beli' => 'required',
            'harga_jual' => 'nullable',
            'diskon' => 'nullable',
            'berat' => 'nullable',
            'satuan' => 'nullable|string',
            'satuan_besar' => 'nullable|string|max:255',
            'konversi_qty' => 'nullable|numeric|min:1',
            'min_stock' => 'nullable|integer|min:0',
            'lokasi' => 'nullable|string',
            'status_produk' => 'required|in:free_produk,tambahan_diskon,free_tester,listing,lunas,belum_lunas,sudah',
            'status_produk_note' => 'nullable|required_if:status_produk,tambahan_diskon|string|max:255',
            'supplier_ids' => 'nullable|array',
            'supplier_ids.*' => 'exists:suppliers,id',
        ];
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
            'harga_jual.required' => 'Harga jual wajib diisi.',
            'satuan.string' => 'Satuan harus berupa teks.',
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
