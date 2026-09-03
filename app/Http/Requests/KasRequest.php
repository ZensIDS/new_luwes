<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Support\IndonesianNumber;

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

    protected function prepareForValidation(): void
    {
        $this->merge(['nominal' => IndonesianNumber::parse($this->input('nominal'))]);
    }
}
