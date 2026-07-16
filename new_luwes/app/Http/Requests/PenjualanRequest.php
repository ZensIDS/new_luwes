<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PenjualanRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'code' => 'nullable',
            'customer_id' => 'nullable',
            'kasir_id' => 'nullable',
            'discount' => 'nullable',
            'total' => 'nullable',
        ];
    }
}
