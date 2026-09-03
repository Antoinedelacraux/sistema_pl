<?php

namespace App\Http\Requests\Stock;

use App\Models\Stock\MovimientoStock;
use App\Services\Stock\ConsumoRecetaService;
use App\Services\Stock\MovimientoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEgresoRequest extends FormRequest
{
    use MovimientoDocumentoRules;

    public function authorize(): bool
    {
        return $this->user()?->puede('registro', 'editar') ?? false;
    }

    public function rules(): array
    {
        return array_merge([
            'id_almacen' => ['required', 'integer', Rule::exists('Almacen', 'idAlmacen')],
            'id_programa' => ['required', 'integer', Rule::exists('Programa', 'idPrograma')],
            'id_presentacion' => ['required', 'integer', Rule::exists('Presentacion', 'idPresentacion')],
            'id_material_pl' => ['required', 'integer', Rule::exists('MaterialProgramaPL', 'idMaterialPL')],
            'id_tipo_consumo' => [
                'required',
                'integer',
                Rule::exists('TipoConsumo', 'idTipoConsumo'),
            ],
            'cantidad' => ['nullable', 'numeric', 'min:0'],
            'pallets_fuera' => ['nullable', 'numeric', 'min:0'],
            'lineas' => ['nullable', 'array'],
            'lineas.*.id_material_pl' => ['required_with:lineas', 'integer', Rule::exists('MaterialProgramaPL', 'idMaterialPL')],
            'lineas.*.cantidad' => ['nullable', 'numeric', 'min:0'],
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

            $movimiento = MovimientoStock::query()
                ->where('estado', 1)
                ->where('tipo', MovimientoStock::TIPO_EGRESO)
                ->whereNull('idTraslado')
                ->find($this->route('idMovimiento'));

            if (! $movimiento) {
                return;
            }

            $receta = app(ConsumoRecetaService::class);
            $idPrograma = (int) $this->input('id_programa');
            $idPresentacion = (int) $this->input('id_presentacion');
            $idMaterial = (int) $this->input('id_material_pl');
            $idAlmacen = (int) $this->input('id_almacen');

            if (! $receta->materialPerteneceAPresentacion($idPrograma, $idPresentacion, $idMaterial)) {
                $validator->errors()->add(
                    'id_material_pl',
                    'El material no pertenece a la presentación seleccionada.'
                );

                return;
            }

            $lineas = $receta->resolverLineasConsumo(
                $idPrograma,
                $idPresentacion,
                $this->input('lineas', []),
                $this->input('pallets_fuera')
            );
            $linea = collect($lineas)->firstWhere('id_material_pl', $idMaterial);
            $cantidad = $linea
                ? (float) $linea['cantidad']
                : (float) $this->input('cantidad');

            if ($cantidad <= 0) {
                $validator->errors()->add(
                    'cantidad',
                    'Indique una cantidad o deje el campo en blanco para usar la cantidad por caja.'
                );

                return;
            }

            $this->merge(['cantidad' => $cantidad]);

            $disponible = app(MovimientoService::class)->stockDisponible($idMaterial, $idAlmacen);

            if ($movimiento->idMaterialPL === $idMaterial && $movimiento->idAlmacen === $idAlmacen) {
                $disponible += (float) $movimiento->cantidad;
            }

            if ((float) $this->input('cantidad') > $disponible) {
                $validator->errors()->add(
                    'cantidad',
                    'Stock insuficiente. Disponible: '.number_format($disponible, 2, '.', '')
                );
            }
        });
    }

    public function attributes(): array
    {
        return array_merge([
            'id_almacen' => 'almacén',
            'id_programa' => 'programa',
            'id_presentacion' => 'presentación',
            'id_material_pl' => 'material',
            'id_tipo_consumo' => 'motivo',
            'cantidad' => 'cantidad',
            'pallets_fuera' => 'pallets (fuera)',
            'fecha' => 'fecha',
            'observacion' => 'observación',
        ], $this->documentoAttributes());
    }

    protected function getRedirectUrl(): string
    {
        return route('registro.index', ['tab' => 'consumos']);
    }
}
