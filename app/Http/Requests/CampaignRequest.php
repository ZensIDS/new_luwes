<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Support\IndonesianNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['superadmin', 'admin-gudang', 'owner'], true);
    }

    public function rules(): array
    {
        return [
            'campaign_type' => ['required', Rule::in(['voucher', 'flash_sale', 'bundle'])],
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'code_auto' => 'nullable|boolean',
            'discount_type' => ['nullable', Rule::in(['percentage', 'nominal'])],
            'discount_value' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'max_qty' => 'nullable|integer|min:1',
            'quota_qty' => 'nullable|integer|min:1',
            'min_purchase' => 'nullable|numeric|min:0',
            'outlet_id' => 'nullable|integer|exists:outlets,id',
            'outlet_ids' => 'nullable|array',
            'outlet_ids.*' => 'integer|exists:outlets,id',
            'daterange' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            'desc' => 'nullable|string',
            'products' => 'nullable|array',
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
            'max_discount_amount' => IndonesianNumber::parse($this->input('max_discount_amount')),
            'min_purchase' => IndonesianNumber::parse($this->input('min_purchase')),
        ]);
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('campaign_type');
            $products = $this->input('products', []);
            $productIds = is_array($products) ? array_map('intval', array_keys($products)) : [];

            if (in_array($type, ['flash_sale', 'bundle'], true) && count($productIds) === 0) {
                $validator->errors()->add('products', 'Pilih minimal satu produk.');
            }

            if (in_array($type, ['voucher', 'flash_sale'], true)
                && (! $this->filled('discount_type') || ! $this->filled('discount_value'))) {
                $validator->errors()->add('discount_value', 'Isi tipe dan nilai potongan.');
            }

            if ($type === 'voucher' && (int) $this->input('quota_qty', 1) > 500) {
                $validator->errors()->add('quota_qty', 'Jumlah kode voucher maksimal 500.');
            }

            if ($productIds && Product::whereIn('id', $productIds)->count() !== count(array_unique($productIds))) {
                $validator->errors()->add('products', 'Satu atau lebih produk tidak ditemukan.');
            }

            if (in_array($type, ['voucher', 'flash_sale'], true)
                && $this->input('discount_type') === 'percentage'
                && (float) $this->input('discount_value', 0) > 100) {
                $validator->errors()->add('discount_value', 'Nilai percentage maksimal 100%.');
            }

            if ($type === 'bundle'
                && (float) $this->input('discount_value', 0) <= 0
                && empty($this->input('bonuses', []))) {
                $validator->errors()->add('discount_value', 'Isi nilai potongan atau tambahkan minimal satu bonus.');
            }
        });
    }
}
