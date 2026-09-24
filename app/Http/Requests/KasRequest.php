<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KasRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required',
            'outlet_id' => 'required',
            'nominal' => 'nullable|numeric',
        ];
    }
}
