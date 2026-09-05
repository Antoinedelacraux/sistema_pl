<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class ParseIngresoExcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->puede('registro', 'crear') ?? false;
    }

    public function rules(): array
    {
        return [
            'archivo_excel' => [
                'required',
                'file',
                'mimes:xlsx,xls,xlsm',
                'max:10240',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'archivo_excel' => 'archivo Excel',
        ];
    }
}
