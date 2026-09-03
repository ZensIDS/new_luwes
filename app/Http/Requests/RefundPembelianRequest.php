<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\IndonesianNumber;

class RefundPembelianRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'code' => 'required',
            'kas_id' => 'required',
            'pembelian_id' => 'nullable',
            'supplier_id' => 'required',
            'outlet_id' => 'required',
            'tanggal' => 'required',
            'total' => 'required',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['total' => IndonesianNumber::parse($this->input('total'))]);
    }
}
