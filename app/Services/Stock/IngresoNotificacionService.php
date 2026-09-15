<?php

namespace App\Services\Stock;

use App\Jobs\EnviarNotificacionIngresoJob;
use App\Models\Programas\MaterialProgramaPL;
use App\Models\Stock\Almacen;

class IngresoNotificacionService
{
    public const PREFIJO_OBS_AJUSTE_TEMPORAL = 'AJUSTE TEMPORAL';

    /**
     * @param  array<string, mixed>  $datos  Validado StoreIngresoRequest
     */
    public function encolarSiCorresponde(array $datos, ?string $registradoPor): void
    {
        if (! config('registro.ingreso.notify_enabled')) {
            return;
        }

        $destinatarios = config('registro.ingreso.notify_to', []);
        if ($destinatarios === []) {
            return;
        }

        $observacion = trim((string) ($datos['observacion'] ?? ''));
        if (
            config('registro.ingreso.skip_ajuste_temporal')
            && $observacion !== ''
            && str_starts_with(mb_strtoupper($observacion), self::PREFIJO_OBS_AJUSTE_TEMPORAL)
        ) {
            return;
        }

        EnviarNotificacionIngresoJob::dispatch(
            $this->armarPayload($datos, $registradoPor)
        )->afterResponse();
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function armarPayload(array $datos, ?string $registradoPor): array
    {
        $idAlmacen = (int) ($datos['id_almacen'] ?? 0);
        $almacen = Almacen::query()->find($idAlmacen);

        $idsMaterial = collect($datos['lineas'] ?? [])
            ->pluck('id_material_pl')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $materiales = $idsMaterial === []
            ? collect()
            : MaterialProgramaPL::query()
                ->whereIn('idMaterialPL', $idsMaterial)
                ->get(['idMaterialPL', 'CodigoPL', 'nomMaterial'])
                ->keyBy('idMaterialPL');

        $lineas = [];
        foreach ($datos['lineas'] ?? [] as $linea) {
            $idMat = (int) ($linea['id_material_pl'] ?? 0);
            $material = $materiales->get($idMat);
            $lineas[] = [
                'codigo_pl' => $material?->CodigoPL ?? '—',
                'nom_material' => $material?->nomMaterial ?? '—',
                'cantidad' => (float) ($linea['cantidad'] ?? 0),
            ];
        }

        return [
            'fecha' => (string) ($datos['fecha'] ?? ''),
            'almacen' => $almacen
                ? trim($almacen->codigo.' — '.$almacen->nomAlmacen)
                : 'Almacén #'.$idAlmacen,
            'factura' => trim((string) ($datos['factura'] ?? '')),
            'guia' => trim((string) ($datos['guia'] ?? '')),
            'observacion' => trim((string) ($datos['observacion'] ?? '')),
            'registrado_por' => $registradoPor ?: 'Sistema',
            'lineas' => $lineas,
            'url_registro' => route('registro.index', ['tab' => 'ingresos']),
        ];
    }
}
