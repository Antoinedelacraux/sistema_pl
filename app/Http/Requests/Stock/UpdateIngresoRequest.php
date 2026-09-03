<?php

namespace App\Http\Requests\Stock;

use App\Models\Stock\MovimientoStock;
use App\Services\Stock\MovimientoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateIngresoRequest extends FormRequest
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
            'fecha' => ['required', 'date'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.id_movimiento' => ['nullable', 'integer'],
            'lineas.*.id_material_pl' => ['required', 'integer', Rule::exists('MaterialProgramaPL', 'idMaterialPL')],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0'],
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
                ->where('tipo', MovimientoStock::TIPO_INGRESO)
                ->whereNull('idTraslado')
                ->find($this->route('idMovimiento'));

            if (! $movimiento) {
                return;
            }

            $service = app(MovimientoService::class);

            try {
                $service->validarEdicionIngreso($movimiento, [
                    'lineas' => $this->input('lineas', []),
                ]);
            } catch (\InvalidArgumentException $exception) {
                $validator->errors()->add('lineas', $exception->getMessage());
            }
        });
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
