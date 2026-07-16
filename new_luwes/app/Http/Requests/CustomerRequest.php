<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required',
            'username' => 'nullable',
            'alamat' => 'required',
            'no_telp' => 'required',
            // 'email' => 'required|email',
            // 'password' => 'required|same:confirm-password',
        ];
    }
}
