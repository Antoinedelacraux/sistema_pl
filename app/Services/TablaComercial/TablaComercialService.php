<?php

namespace App\Services\TablaComercial;

use App\Models\Programas\TablaComercialDia;
use App\Models\Programas\TablaComercialEstado;
use App\Models\Stock\MovimientoStock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TablaComercialService
{
    /**
     * Devuelve estados guardados keyed por "{idPg}_{idSem}".
     *
     * @param  int[]  $semIds
     * @param  int[]  $pgIds
     * @return array<string, string>
     */
    public function estadosBulk(array $semIds, array $pgIds): array
    {
        if (empty($semIds) || empty($pgIds)) {
            return [];
        }

        return TablaComercialEstado::query()
            ->whereIn('idSemProduccion', $semIds)
            ->whereIn('idProgramaGeneral', $pgIds)
            ->get(['idProgramaGeneral', 'idSemProduccion', 'estado'])
            ->mapWithKeys(fn ($r) => [$r->idProgramaGeneral.'_'.$r->idSemProduccion => $r->estado])
            ->all();
    }

    /**
     * Devuelve suma de egresos por combinación PG+semana,
     * keyed por "{idPg}_{idSem}" → float.
     * Un query; el mapeo fecha→sem se hace en PHP.
     *
     * @param  Collection  $semanas  Collection<SemProduccion>
     * @param  int[]       $pgIds
     * @return array<string, float>
     */
    public function egresosBulk(Collection $semanas, array $pgIds): array
    {
        if ($semanas->isEmpty() || empty($pgIds)) {
            return [];
        }

        $minFecha = $semanas->min('fechaInicio');
        $maxFecha = $semanas->max('fechaInicio')?->addDays(6);

        if (! $minFecha || ! $maxFecha) {
            return [];
        }

        // Egresos por PG y fecha agrupados (un solo query)
        $rows = DB::table('MovimientoStock as ms')
            ->join('Programa as p', 'p.idPrograma', '=', 'ms.idPrograma')
            ->whereIn('p.idProgramaGeneral', $pgIds)
            ->where('ms.tipo', MovimientoStock::TIPO_EGRESO)
            ->where('ms.estado', 1)
            ->whereNotNull('ms.idPrograma')
            ->whereBetween('ms.fecha', [$minFecha, $maxFecha])
            ->select('p.idProgramaGeneral', 'ms.fecha', DB::raw('SUM(ms.cantidad) as total'))
            ->groupBy('p.idProgramaGeneral', 'ms.fecha')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // Construir mapa fecha → idSemProduccion
        $fechaSemMap = [];
        foreach ($semanas as $sem) {
            $inicio = $sem->fechaInicio?->toDateString();
            if (! $inicio) {
                continue;
            }
            for ($d = 0; $d <= 6; $d++) {
                $dia = date('Y-m-d', strtotime("+{$d} days", strtotime($inicio)));
                $fechaSemMap[$dia] = $sem->idSemProduccion;
            }
        }

        $resultado = [];
        foreach ($rows as $row) {
            $fecha = is_string($row->fecha)
                ? $row->fecha
                : $row->fecha->toDateString();

            $idSem = $fechaSemMap[$fecha] ?? null;
            if ($idSem === null) {
                continue;
            }

            $k = $row->idProgramaGeneral.'_'.$idSem;
            $resultado[$k] = ($resultado[$k] ?? 0.0) + (float) $row->total;
        }

        return $resultado;
    }

    /**
     * Asignaciones y estados de una semana, keyed por idProgramaGeneral (string).
     *
     * @return array<string, array{dia: int|null, estado: string|null}>
     */
    public function semanaBulk(int $idSem): array
    {
        $asignaciones = TablaComercialDia::query()
            ->where('idSemProduccion', $idSem)
            ->get(['idProgramaGeneral', 'dia']);

        $estados = TablaComercialEstado::query()
            ->where('idSemProduccion', $idSem)
            ->pluck('estado', 'idProgramaGeneral');

        $result = [];

        foreach ($asignaciones as $row) {
            $idPg = (int) $row->idProgramaGeneral;
            $result[(string) $idPg] = [
                'dia' => (int) $row->dia,
                'estado' => $estados->get($idPg),
            ];
        }

        foreach ($estados as $idPg => $estado) {
            $key = (string) (int) $idPg;
            if (! isset($result[$key])) {
                $result[$key] = [
                    'dia' => null,
                    'estado' => $estado,
                ];
            }
        }

        return $result;
    }

    public function asignarDia(int $idPg, int $idSem, ?int $dia): void
    {
        if ($dia === null) {
            TablaComercialDia::query()
                ->where('idProgramaGeneral', $idPg)
                ->where('idSemProduccion', $idSem)
                ->delete();

            return;
        }

        TablaComercialDia::updateOrCreate(
            ['idProgramaGeneral' => $idPg, 'idSemProduccion' => $idSem],
            ['dia' => $dia, 'Estado' => 1]
        );
    }

    /**
     * Guarda o quita el estado manual de un PG en una semana.
     */
    public function updateEstado(int $idPg, int $idSem, ?string $estado): void
    {
        if ($estado === null) {
            TablaComercialEstado::query()
                ->where('idProgramaGeneral', $idPg)
                ->where('idSemProduccion', $idSem)
                ->delete();

            return;
        }

        TablaComercialEstado::updateOrCreate(
            ['idProgramaGeneral' => $idPg, 'idSemProduccion' => $idSem],
            ['estado' => $estado]
        );
    }
}
