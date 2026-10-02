<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreIngresoRequest extends FormRequest
{
    use MovimientoDocumentoRules;
    use ValidaMaterialesEnLote;

    public function authorize(): bool
    {
        return $this->user()?->puede('registro', 'crear') ?? false;
    }

    public function rules(): array
    {
        return array_merge([
            'id_almacen' => ['required', 'integer', Rule::exists('Almacen', 'idAlmacen')],
            'fecha' => ['required', 'date'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.id_material_pl' => ['required', 'integer', Rule::exists('MaterialProgramaPL', 'idMaterialPL')],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0'],
        ], $this->documentoRules());
    }

    public function withValidator(Validator $validator): void
    {
        $this->validarMaterialesEnLote($validator);
    }

    public function attributes(): array
    {
        return array_merge([
            'id_almacen' => 'almacén',
            'fecha' => 'fecha',
            'observacion' => 'observación',
            'factura' => 'factura',
            'guia' => 'guía',
            'lineas' => 'materiales',
            'lineas.*.id_material_pl' => 'material',
            'lineas.*.cantidad' => 'cantidad',
        ], $this->documentoAttributes());
    }

    protected function getRedirectUrl(): string
    {
        return route('registro.index', ['tab' => 'ingresos']);
    }
}
