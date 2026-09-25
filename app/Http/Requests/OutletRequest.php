<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OutletRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'logo' => 'nullable',
            'name' => 'required',
            'alamat' => 'required',
            // 'npwp' => 'required',
            // 'slogan' => 'required',
            'desc' => 'nullable',
            // 'footer' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Nama outlet wajib diisi.',
            'alamat.required' => 'Alamat outlet wajib diisi.',
        ];
    }
}
