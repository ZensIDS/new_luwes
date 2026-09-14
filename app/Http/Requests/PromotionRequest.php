<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Support\IndonesianNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['superadmin', 'admin-gudang', 'owner'], true);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => ['nullable', 'string', 'max:100', Rule::unique('promotions', 'code')->ignore($this->route('promotion'))],
            'type' => ['required', Rule::in(['flash_sale', 'bundle'])],
            'discount_type' => ['nullable', Rule::in(['percentage', 'nominal'])],
            'discount_value' => 'nullable|numeric|min:0',
            'bundle_price' => 'nullable|numeric|min:0',
            'max_qty' => 'nullable|integer|min:1',
            'quota_qty' => 'nullable|integer|min:1',
            'min_purchase' => 'nullable|numeric|min:0',
            'outlet_id' => 'nullable|integer|exists:outlets,id',
            'outlet_ids' => 'nullable|array',
            'outlet_ids.*' => 'integer|exists:outlets,id',
            'daterange' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'stackable' => 'nullable|boolean',
            'desc' => 'nullable|string',
            'products' => 'required|array|min:1',
            'products.*' => 'numeric|min:0.01',
            'bonuses' => 'nullable|array',
            'bonuses.*.name' => 'required|string|max:255',
            'bonuses.*.qty' => 'required|integer|min:1|max:999999',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'discount_value' => IndonesianNumber::parse($this->input('discount_value')),
            'bundle_price' => IndonesianNumber::parse($this->input('bundle_price')),
            'min_purchase' => IndonesianNumber::parse($this->input('min_purchase')),
        ]);
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $productIds = array_map('intval', array_keys($this->input('products', [])));
            if ($productIds && Product::whereIn('id', $productIds)->count() !== count(array_unique($productIds))) {
                $validator->errors()->add('products', 'Satu atau lebih produk promo tidak ditemukan.');
            }
            if ($this->input('type') === 'flash_sale' && $this->input('discount_type') === 'percentage'
                && (float) $this->input('discount_value', 0) > 100) {
                $validator->errors()->add('discount_value', 'Nilai percentage maksimal 100%.');
            }
            if ($this->input('type') === 'bundle'
                && (float) $this->input('bundle_price', 0) <= 0
                && empty($this->input('bonuses', []))) {
                $validator->errors()->add('bundle_price', 'Isi potongan bundle atau tambahkan minimal satu barang bonus.');
            }
        });
    }
}
