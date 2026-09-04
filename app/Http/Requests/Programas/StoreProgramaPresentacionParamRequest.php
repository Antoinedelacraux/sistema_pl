<?php

namespace App\Http\Requests\Programas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgramaPresentacionParamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->puede('programas', 'editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'id_programa' => ['required', 'integer', Rule::exists('Programa', 'idPrograma')],
            'id_presentacion' => ['required', 'integer', Rule::exists('Presentacion', 'idPresentacion')],
            'pallets' => ['nullable', 'numeric', 'min:0'],
            'conformacion' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_programa' => 'programa',
            'id_presentacion' => 'producto',
            'pallets' => 'Pallets (Fuera)',
            'conformacion' => 'Conformación (Dentro)',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('programas.presentacion-params.index');
    }
}
