<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PengeluaranRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'category_id' => 'required',
            'tanggal' => 'required',
            // 'biaya' => 'required',
            'desc' => 'required',
            'kas_id' => 'required',
            'jumlah' => 'required',
        ];
    }
}
