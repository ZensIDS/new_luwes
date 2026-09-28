<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PembelianRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        $this->merge([
            'total' => $this->cleanNumeric($this->total),
        ]);

        if ($this->has('product')) {
            $products = $this->product;
            foreach ($products as $key => $product) {
                if (isset($product['harga_beli'])) {
                    $products[$key]['harga_beli'] = $this->cleanNumeric($product['harga_beli']);
                }
                if (isset($product['subtotal'])) {
                    $products[$key]['subtotal'] = $this->cleanNumeric($product['subtotal']);
                }
            }
            $this->merge(['product' => $products]);
        }
    }

    private function cleanNumeric($value)
    {
        // Remove all non-digit characters (thousand separators: comma or dot)
        return preg_replace('/[^\d]/', '', $value);
    }

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            // 'code' => 'required',
            // 'outlet_id' => 'required',
            'supplier_id' => 'required|exists:suppliers,id',
            // 'kas_id' => 'required',
            // 'qty' => 'required',
            // 'expired' => 'required',
            // 'harga_beli' => 'required',
            'subtotal' => 'nullable',
            'total' => 'nullable',
            'product' => 'required|array',
            'product.*.product_id' => 'required|exists:products,id',
            'product.*.qty' => 'required|numeric|min:1',
            'product.*.harga_beli' => 'required|min:0',
            'product.*.subtotal' => 'required|min:0',
            'product.*.serial_numbers' => 'nullable|string',
        ];
    }

    public function messages()
    {
        return [
            'code.required' => 'Kode pembelian wajib diisi.',

            'supplier_id.required' => 'Supplier wajib dipilih.',
            'supplier_id.exists' => 'Supplier yang dipilih tidak valid.',

            'product.required' => 'Produk wajib diisi.',
            'product.array' => 'Format produk tidak valid.',

            'product.*.product_id.required' => 'ID produk wajib diisi.',
            'product.*.product_id.exists' => 'Produk yang dipilih tidak valid.',

            'product.*.qty.required' => 'Jumlah produk wajib diisi.',
            'product.*.qty.numeric' => 'Jumlah produk harus berupa angka.',
            'product.*.qty.min' => 'Jumlah produk minimal 1.',

            'product.*.harga_beli.required' => 'Harga beli produk wajib diisi.',
            'product.*.harga_beli.min' => 'Harga beli tidak boleh kurang dari 0.',

            'product.*.subtotal.required' => 'Subtotal produk wajib diisi.',
            'product.*.subtotal.min' => 'Subtotal tidak boleh kurang dari 0.',

            'product.*.serial_numbers.string' => 'Nomor seri harus berupa teks.',
        ];
    }
}
