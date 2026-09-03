<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\IndonesianNumber;

class OutletPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['superadmin', 'admin-gudang', 'owner'], true);
    }

    public function rules(): array
    {
        return [
            'outlet_id' => 'required|exists:outlets,id',
            'product_id' => 'required|exists:products,id',
            'disc_brand_type' => ['required', Rule::in(['nominal', 'percentage'])],
            'disc_brand_value' => 'required|numeric|min:0',
            'margin_type' => ['required', Rule::in(['nominal', 'percentage'])],
            'margin_value' => 'required|numeric|min:0',
            'disc_toko_type' => ['required', Rule::in(['nominal', 'percentage'])],
            'disc_toko_value' => 'required|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after_or_equal:effective_from',
            'is_active' => 'nullable|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'disc_brand_value' => IndonesianNumber::parse($this->input('disc_brand_value')),
            'margin_value' => IndonesianNumber::parse($this->input('margin_value')),
            'disc_toko_value' => IndonesianNumber::parse($this->input('disc_toko_value')),
        ]);
    }
}
