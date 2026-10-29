<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentMethodRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_fixed_amount' => $this->boolean('is_fixed_amount'),
            // Terima koma desimal (0,7) maupun titik (0.7); kosong = tanpa biaya.
            'charge_percent' => $this->filled('charge_percent')
                ? str_replace(',', '.', trim((string) $this->input('charge_percent')))
                : 0,
        ]);
    }

    public function rules()
    {
        return [
            'name' => 'required',
            'bank_number' => 'required',
            'desc' => 'nullable',
            'is_fixed_amount' => 'boolean',
            'charge_percent' => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function messages()
    {
        return [
            'charge_percent.numeric' => 'Biaya tambahan harus berupa angka (persen).',
            'charge_percent.min' => 'Biaya tambahan tidak boleh negatif.',
            'charge_percent.max' => 'Biaya tambahan maksimal 100%.',
        ];
    }
}