<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
}
