<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class ParseIngresoXmlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->puede('registro', 'crear') ?? false;
    }

    public function rules(): array
    {
        return [
            'archivo_xml' => ['required', 'file', 'mimes:xml,text/xml,application/xml', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return [
            'archivo_xml' => 'archivo XML',
        ];
    }
}
