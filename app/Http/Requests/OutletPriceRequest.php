<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\IndonesianNumber;

class OutletPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['superadmin', 'admin-gudang', 'owner', 'staff-outlet'], true);
    }

    public function rules(): array
    {
        return [
            'outlet_id' => 'required|exists:outlets,id',
            'product_id' => 'required|exists:products,id',
            'disc_brand_type' => ['required', Rule::in(['nominal', 'percentage'])],
            'disc_brand_value' => 'required|numeric|min:0',
            'disc_tambahan_type' => ['nullable', Rule::in(['nominal', 'percentage'])],
            'disc_tambahan_value' => 'nullable|numeric|min:0',
            'disc_tambahan_2_type' => ['nullable', Rule::in(['nominal', 'percentage'])],
            'disc_tambahan_2_value' => 'nullable|numeric|min:0',
            'disc_tambahan_3_type' => ['nullable', Rule::in(['nominal', 'percentage'])],
            'disc_tambahan_3_value' => 'nullable|numeric|min:0',
            'disc_tambahan_4_type' => ['nullable', Rule::in(['nominal', 'percentage'])],
            'disc_tambahan_4_value' => 'nullable|numeric|min:0',
            'margin_type' => ['required', Rule::in(['nominal', 'percentage'])],
            'margin_value' => 'required|numeric|min:0',
            'disc_toko_type' => ['nullable', Rule::in(['nominal', 'percentage'])],
            'disc_toko_value' => 'nullable|numeric|min:0',
            'pajak_type' => ['nullable', Rule::in(['nominal', 'percentage'])],
            'pajak_value' => 'nullable|numeric|min:0',
            'outlet_adjustment_type' => ['nullable', Rule::in(['nominal', 'percentage'])],
            'outlet_adjustment_value' => 'nullable|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after_or_equal:effective_from',
            'is_active' => 'nullable|boolean',
            'note' => 'nullable|string|max:500',
            'hpp' => 'nullable|numeric|min:0',
            'hpp_changed' => 'nullable|boolean',
            'hpp_unit' => ['nullable', Rule::in(['kecil', 'besar'])],
            'hpp_besar' => 'nullable|numeric|min:0',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'disc_brand_value' => IndonesianNumber::parse($this->input('disc_brand_value')) ?: 0,
            'disc_tambahan_value' => IndonesianNumber::parse($this->input('disc_tambahan_value')) ?: 0,
            'disc_tambahan_2_value' => IndonesianNumber::parse($this->input('disc_tambahan_2_value')) ?: 0,
            'disc_tambahan_3_value' => IndonesianNumber::parse($this->input('disc_tambahan_3_value')) ?: 0,
            'disc_tambahan_4_value' => IndonesianNumber::parse($this->input('disc_tambahan_4_value')) ?: 0,
            'margin_value' => IndonesianNumber::parse($this->input('margin_value')) ?: 0,
            'disc_toko_value' => IndonesianNumber::parse($this->input('disc_toko_value')) ?: 0,
            'pajak_value' => IndonesianNumber::parse($this->input('pajak_value')) ?: 0,
            'outlet_adjustment_value' => IndonesianNumber::parse($this->input('outlet_adjustment_value')) ?: 0,
            'hpp' => IndonesianNumber::parse($this->input('hpp')),
            'hpp_besar' => IndonesianNumber::parse($this->input('hpp_besar')),
            'note' => $this->filled('note') ? trim((string) $this->input('note')) : null,
        ]);
    }
}