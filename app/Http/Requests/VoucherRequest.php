<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\IndonesianNumber;

class VoucherRequest extends FormRequest
{
    public function authorize()
    {
        return in_array($this->user()?->role, ['superadmin', 'admin-gudang', 'owner'], true);
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:100',
            'type' => 'required|in:nominal,percentage',
            'value' => 'required|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'outlet_id' => 'nullable|integer|exists:outlets,id',
            'daterange' => 'nullable|string',
            'desc' => 'nullable|string',
            'product_id' => 'nullable|exists:products,id',
            'kasir_id' => 'nullable|exists:users,id',
            'quantity' => 'nullable|integer|min:1|max:500',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'value' => IndonesianNumber::parse($this->input('value')),
            'min_purchase' => IndonesianNumber::parse($this->input('min_purchase')),
            'max_discount_amount' => IndonesianNumber::parse($this->input('max_discount_amount')),
        ]);
    }

    protected function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->input('type') === 'percentage' && (float) $this->input('value', 0) > 100) {
                $validator->errors()->add('value', 'Nilai percentage maksimal 100%.');
            }
        });
    }
}
