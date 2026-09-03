<?php

namespace App\Http\Requests\Stock;

use App\Models\Stock\Traslado;
use App\Services\Stock\MovimientoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTrasladoRequest extends FormRequest
{
    use MovimientoDocumentoRules;

    public function authorize(): bool
    {
        return $this->user()?->puede('registro', 'editar') ?? false;
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

            $traslado = Traslado::query()
                ->where('estado', 1)
                ->find($this->route('idTraslado'));

            if (! $traslado) {
                return;
            }

            $idMaterial = (int) $this->input('id_material_pl');
            $idOrigen = (int) $this->input('id_almacen_origen');
            $disponible = app(MovimientoService::class)->stockDisponible($idMaterial, $idOrigen);

            if ($traslado->idMaterialPL === $idMaterial && $traslado->idAlmacenOrigen === $idOrigen) {
                $disponible += (float) $traslado->cantidad;
            }

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
