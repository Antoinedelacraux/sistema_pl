<?php

namespace App\Http\Requests\Mrp;

use Illuminate\Foundation\Http\FormRequest;

class ImportMrpExcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->puede('mrp', 'crear') ?? false;
    }

    public function rules(): array
    {
        return [
            'archivo_excel' => [
                'required',
                'file',
                'mimes:xlsx,xlsm,xls',
                'max:'.(int) config('power_automate.max_upload_kb', 10240),
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
