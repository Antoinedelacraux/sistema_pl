<?php

namespace App\Http\Requests\Stock;

use App\Services\Stock\ConsumoRecetaService;
use App\Services\Stock\MovimientoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEgresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->puede('registro', 'crear') ?? false;
    }

    public function rules(): array
    {
        return [
            'id_almacen' => ['required', 'integer', Rule::exists('Almacen', 'idAlmacen')],
            'id_programa' => ['required', 'integer', Rule::exists('Programa', 'idPrograma')],
            'id_presentacion' => ['required', 'integer', Rule::exists('Presentacion', 'idPresentacion')],
            'id_tipo_consumo' => [
                'required',
                'integer',
                Rule::exists('TipoConsumo', 'idTipoConsumo')->where(fn ($q) => $q->where('Estado', 1)),
            ],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.id_material_pl' => ['required', 'integer', Rule::exists('MaterialProgramaPL', 'idMaterialPL')],
            'lineas.*.cantidad' => ['nullable', 'numeric', 'min:0'],
            'lineas.*.total' => ['nullable', 'numeric', 'min:0'],
            'conformacion_dentro' => ['nullable', 'numeric', 'min:0'],
            'pallets_fuera' => ['nullable', 'numeric', 'min:0'],
            'fecha' => ['required', 'date'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $receta = app(ConsumoRecetaService::class);
            $idPrograma = (int) $this->input('id_programa');
            $idPresentacion = (int) $this->input('id_presentacion');
            $idAlmacen = (int) $this->input('id_almacen');
            $lineas = $receta->resolverLineasConsumo(
                $idPrograma,
                $idPresentacion,
                $this->input('lineas', []),
                $this->input('pallets_fuera'),
                $this->input('conformacion_dentro')
            );

            if ($lineas === []) {
                $validator->errors()->add(
                    'lineas',
                    'No hay cantidades para retirar. Complete la receta o indique otra cantidad.'
                );

                return;
            }

            $movimiento = app(MovimientoService::class);
            foreach ($lineas as $linea) {
                $disponible = $movimiento->stockDisponible((int) $linea['id_material_pl'], $idAlmacen);
                if ($linea['cantidad'] > $disponible) {
                    $nombre = $linea['nom_material'] ?: 'material';
                    $faltante = max(0, round((float) $linea['cantidad'] - $disponible, 2));
                    $validator->errors()->add(
                        'lineas',
                        'Stock insuficiente de '.$nombre.'. Disponible: '.number_format($disponible, 2, '.', '')
                        .($faltante > 0 ? ' · Faltan: '.number_format($faltante, 2, '.', '') : '')
                    );
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'id_almacen' => 'almacén',
            'id_programa' => 'programa',
            'id_presentacion' => 'producto',
            'id_tipo_consumo' => 'motivo',
            'lineas' => 'materiales',
            'lineas.*.id_material_pl' => 'material',
            'lineas.*.cantidad' => 'otra cantidad',
            'lineas.*.total' => 'total',
            'fecha' => 'fecha',
            'observacion' => 'observación',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('registro.index', ['tab' => 'consumos']);
    }
}
