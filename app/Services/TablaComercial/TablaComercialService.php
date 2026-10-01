<?php

namespace App\Services\TablaComercial;

use App\Models\Estado;
use App\Models\Programas\TablaComercialDia;
use App\Models\Programas\TablaComercialEstado;
use App\Models\Stock\MovimientoStock;
use App\Support\FamiliaMaterialSupport;
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

        $rangos = [];
        $fechaMin = null;
        $fechaMax = null;

        foreach ($semanas as $sem) {
            if ($sem->fechaInicio === null) {
                continue;
            }

            $inicio = $sem->fechaInicio->toDateString();
            $fin = $sem->fechaInicio->copy()->addDays(6)->toDateString();
            $rangos[] = [
                'id' => (int) $sem->idSemProduccion,
                'inicio' => $inicio,
                'fin' => $fin,
            ];
            $fechaMin = $fechaMin === null || $inicio < $fechaMin ? $inicio : $fechaMin;
            $fechaMax = $fechaMax === null || $fin > $fechaMax ? $fin : $fechaMax;
        }

        if ($rangos === [] || $fechaMin === null || $fechaMax === null) {
            return [];
        }

        $filas = DB::table('MovimientoStock as ms')
            ->join('Programa as p', 'p.idPrograma', '=', 'ms.idPrograma')
            ->join('ProgramaFila as pf', function ($join) {
                $join->on('pf.idPrograma', '=', 'ms.idPrograma')
                    ->on('pf.idPresentacion', '=', 'ms.idPresentacion')
                    ->on('pf.idMaterialPL', '=', 'ms.idMaterialPL')
                    ->where('pf.Estado', Estado::ACTIVO);
            })
            ->join('Familia as f', 'f.idFamilia', '=', 'pf.idFamilia')
            ->whereIn('p.idProgramaGeneral', $pgIds)
            ->where('ms.tipo', MovimientoStock::TIPO_EGRESO)
            ->where('ms.estado', 1)
            ->whereNull('ms.idTraslado')
            ->whereBetween('ms.fecha', [$fechaMin, $fechaMax])
            ->whereNotNull('ms.idPrograma')
            ->whereRaw("LOWER(TRIM(f.nomFamilia)) IN ('cajas', 'caja', 'box')")
            ->get([
                'p.idProgramaGeneral',
                'ms.idPrograma',
                'ms.idPresentacion',
                'ms.fecha',
                'ms.fechaRegistro',
                'ms.idUsuario',
                'ms.idTipoConsumo',
                'ms.cantidad',
                'f.nomFamilia',
            ]);

        $porLote = [];

        foreach ($filas as $row) {
            if (! FamiliaMaterialSupport::esCajas($row->nomFamilia ?? null)) {
                continue;
            }

            $fecha = (string) $row->fecha;
            $fechaDia = substr($fecha, 0, 10);

            foreach ($rangos as $rango) {
                if ($fechaDia < $rango['inicio'] || $fechaDia > $rango['fin']) {
                    continue;
                }

                $lote = implode('|', [
                    (int) $row->idProgramaGeneral,
                    $rango['id'],
                    (int) $row->idPrograma,
                    (int) $row->idPresentacion,
                    $fecha,
                    (string) $row->fechaRegistro,
                    (int) ($row->idUsuario ?? 0),
                    (int) ($row->idTipoConsumo ?? 0),
                ]);
                $cantidad = (float) $row->cantidad;
                $porLote[$lote] = isset($porLote[$lote]) ? max($porLote[$lote], $cantidad) : $cantidad;
            }
        }

        $resultado = [];
        foreach ($porLote as $lote => $cantidad) {
            $partes = explode('|', $lote, 3);
            $clave = $partes[0].'_'.$partes[1];
            if ($cantidad <= 0) {
                continue;
            }
            $resultado[$clave] = ($resultado[$clave] ?? 0) + $cantidad;
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
