<?php

namespace App\Http\Requests\Programas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgramaFilaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->puede('programas', 'crear') ?? false;
    }

    public function rules(): array
    {
        return [
            'id_mercado' => ['required', 'integer', Rule::exists('Mercado', 'idMercado')],
            'id_cliente' => ['required', 'integer', Rule::exists('Cliente', 'idCliente')],
            'id_variedad' => ['required', 'integer', Rule::exists('Variedad', 'idVariedad')],
            'id_programa' => ['required', 'integer', Rule::exists('Programa', 'idPrograma')],
            'id_presentacion' => ['required', 'integer', Rule::exists('Presentacion', 'idPresentacion')],
            'id_pre_nisira' => ['required', 'integer', Rule::exists('PreNisira', 'idPreNisira')],
            'codigo_pl' => ['required', 'string', 'max:20'],
            'nom_material' => ['required', 'string', 'max:255'],
            'cantidad' => ['required', 'numeric', 'min:0'],
            'id_uso' => ['required', 'integer', Rule::exists('Uso', 'idUso')],
            'id_situado' => ['required', 'integer', Rule::exists('Situado', 'idSituado')],
            'id_familia' => ['required', 'integer', Rule::exists('Familia', 'idFamilia')],
            'imagen' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_mercado' => 'mercado',
            'id_cliente' => 'cliente',
            'id_variedad' => 'variedad',
            'id_programa' => 'programa',
            'id_presentacion' => 'presentación',
            'id_pre_nisira' => 'presentación Nisira',
            'codigo_pl' => 'código de planificación',
            'nom_material' => 'material',
            'cantidad' => 'cantidad por caja',
            'id_uso' => 'uso',
            'id_situado' => 'situado',
            'id_familia' => 'familia',
            'imagen' => 'imagen',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('programas.index');
    }
}
