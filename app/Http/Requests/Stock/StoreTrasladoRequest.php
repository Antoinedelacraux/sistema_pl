<?php

namespace App\Http\Requests\Stock;

use App\Services\Stock\MovimientoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTrasladoRequest extends FormRequest
{
    use MovimientoDocumentoRules;

    public function authorize(): bool
    {
        return $this->user()?->puede('registro', 'crear') ?? false;
    }

    public function rules(): array
    {
        return array_merge([
            'id_almacen_origen' => ['required', 'integer', Rule::exists('Almacen', 'idAlmacen'), 'different:id_almacen_destino'],
            'id_almacen_destino' => ['required', 'integer', Rule::exists('Almacen', 'idAlmacen'), 'different:id_almacen_origen'],
            'id_material_pl' => ['required', 'integer', Rule::exists('MaterialProgramaPL', 'idMaterialPL')],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'fecha' => ['required', 'date'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ], $this->documentoRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $disponible = app(MovimientoService::class)->stockDisponible(
                (int) $this->input('id_material_pl'),
                (int) $this->input('id_almacen_origen')
            );

            if ((float) $this->input('cantidad') > $disponible) {
                $validator->errors()->add(
                    'cantidad',
                    'Stock insuficiente en origen. Disponible: '.number_format($disponible, 2, '.', '')
                );
            }
        });
    }

    public function attributes(): array
    {
        return array_merge([
            'id_almacen_origen' => 'almacén origen',
            'id_almacen_destino' => 'almacén destino',
            'id_material_pl' => 'material',
            'cantidad' => 'cantidad',
            'fecha' => 'fecha',
            'observacion' => 'observación',
        ], $this->documentoAttributes());
    }

    protected function getRedirectUrl(): string
    {
        return route('registro.index', ['tab' => 'traslados']);
    }
}
