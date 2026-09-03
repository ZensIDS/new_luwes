<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\IndonesianNumber;

class RefundRequest extends FormRequest
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
            'penjualan_id' => 'required',
            'customer_id' => 'required',
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
