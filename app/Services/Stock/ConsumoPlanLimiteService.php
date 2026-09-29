<?php

namespace App\Services\Stock;

use App\Models\Estado;
use App\Models\Programas\PlanProduccion;
use App\Models\Programas\ProgramaCatalogo;
use App\Models\Programas\SemProduccion;
use App\Models\Stock\MovimientoStock;
use App\Support\FamiliaMaterialSupport;
use Illuminate\Support\Facades\DB;

class ConsumoPlanLimiteService
{
    /** @var array<string, int>|null */
    private static ?array $mapaFechaSem = null;

    public function __construct(
        private readonly ConsumoRecetaService $consumoRecetaService,
    ) {}

    /**
     * Plan semanal (Plan de Producción) vs cajas ya consumidas para el programa general del catálogo.
     *
     * @param  list<int>  $excluirIdsMovimiento
     * @return array{
     *   aplica: bool,
     *   plan: float,
     *   consumido: float,
     *   disponible: float,
     *   semana: string|null
     * }
     */
    public function resumen(int $idPrograma, string $fecha, array $excluirIdsMovimiento = []): array
    {
        $vacio = [
            'aplica' => false,
            'plan' => 0.0,
            'consumido' => 0.0,
            'disponible' => 0.0,
            'semana' => null,
        ];

        if ($idPrograma <= 0) {
            return $vacio;
        }

        $idPg = (int) ProgramaCatalogo::query()->whereKey($idPrograma)->value('idProgramaGeneral');
        if ($idPg <= 0) {
            return $vacio;
        }

        $idSem = $this->idSemProduccionParaFecha($fecha);
        if ($idSem === null) {
            return $vacio;
        }

        $plan = (float) PlanProduccion::query()
            ->activo()
            ->where('idProgramaGeneral', $idPg)
            ->where('idSemProduccion', $idSem)
            ->value('cantidad');

        if ($plan <= 0) {
            return $vacio;
        }

        $consumido = $this->cajasConsumidasProgramaGeneralSemana($idPg, $idSem, $excluirIdsMovimiento);
        $semana = SemProduccion::query()->whereKey($idSem)->value('codigoSemana');

        return [
            'aplica' => true,
            'plan' => $plan,
            'consumido' => $consumido,
            'disponible' => max(0.0, $plan - $consumido),
            'semana' => $semana !== null ? (string) $semana : null,
        ];
    }

    /**
     * @param  list<int>  $excluirIdsMovimiento
     * @return array{
     *   aplica: bool,
     *   plan: float,
     *   consumido: float,
     *   solicitado: float,
     *   disponible: float,
     *   id_programa_general: int|null,
     *   id_sem_produccion: int|null,
     *   semana: string|null,
     *   mensaje: string|null
     * }
     */
    public function evaluar(
        int $idPrograma,
        int $idPresentacion,
        string $fecha,
        array $lineasInput,
        mixed $palletsInput = null,
        mixed $conformacionInput = null,
        array $excluirIdsMovimiento = []
    ): array {
        $vacio = [
            'aplica' => false,
            'plan' => 0.0,
            'consumido' => 0.0,
            'solicitado' => 0.0,
            'disponible' => 0.0,
            'id_programa_general' => null,
            'id_sem_produccion' => null,
            'semana' => null,
            'mensaje' => null,
        ];

        if ($idPrograma <= 0) {
            return $vacio;
        }

        $idPg = (int) ProgramaCatalogo::query()->whereKey($idPrograma)->value('idProgramaGeneral');
        if ($idPg <= 0) {
            return $vacio;
        }

        $idSem = $this->idSemProduccionParaFecha($fecha);
        if ($idSem === null) {
            return $vacio;
        }

        $plan = (float) PlanProduccion::query()
            ->activo()
            ->where('idProgramaGeneral', $idPg)
            ->where('idSemProduccion', $idSem)
            ->value('cantidad');

        if ($plan <= 0) {
            return $vacio;
        }

        $consumido = $this->cajasConsumidasProgramaGeneralSemana($idPg, $idSem, $excluirIdsMovimiento);
        $solicitado = $this->consumoRecetaService->cajasSolicitadasEnLineas(
            $idPrograma,
            $idPresentacion,
            $lineasInput,
            $palletsInput,
            $conformacionInput
        );

        $disponible = max(0.0, $plan - $consumido);
        $semana = SemProduccion::query()->whereKey($idSem)->value('codigoSemana');

        $resultado = [
            'aplica' => true,
            'plan' => $plan,
            'consumido' => $consumido,
            'solicitado' => $solicitado,
            'disponible' => $disponible,
            'id_programa_general' => $idPg,
            'id_sem_produccion' => $idSem,
            'semana' => $semana !== null ? (string) $semana : null,
            'mensaje' => null,
        ];

        if ($solicitado > 0 && ($consumido + $solicitado) > $plan + 0.0001) {
            $resultado['mensaje'] = sprintf(
                'Plan %s: %s cajas. Ya registrado: %s. Máximo en este consumo: %s (intentó %s).',
                $semana ?: 'semana',
                $this->formatearCajas($plan),
                $this->formatearCajas($consumido),
                $this->formatearCajas($disponible),
                $this->formatearCajas($solicitado)
            );
        }

        return $resultado;
    }

    /**
     * @param  list<int>  $excluirIdsMovimiento
     */
    public function cajasConsumidasProgramaGeneralSemana(
        int $idProgramaGeneral,
        int $idSemProduccion,
        array $excluirIdsMovimiento = []
    ): float {
        $semana = SemProduccion::query()->find($idSemProduccion);
        if ($semana === null || $semana->fechaInicio === null) {
            return 0.0;
        }

        $inicio = $semana->fechaInicio->toDateString();
        $fin = $semana->fechaInicio->copy()->addDays(6)->toDateString();

        $query = DB::table('MovimientoStock as ms')
            ->join('Programa as p', 'p.idPrograma', '=', 'ms.idPrograma')
            ->join('ProgramaFila as pf', function ($join) {
                $join->on('pf.idPrograma', '=', 'ms.idPrograma')
                    ->on('pf.idPresentacion', '=', 'ms.idPresentacion')
                    ->on('pf.idMaterialPL', '=', 'ms.idMaterialPL')
                    ->where('pf.Estado', Estado::ACTIVO);
            })
            ->join('Familia as f', 'f.idFamilia', '=', 'pf.idFamilia')
            ->where('p.idProgramaGeneral', $idProgramaGeneral)
            ->where('ms.tipo', MovimientoStock::TIPO_EGRESO)
            ->where('ms.estado', 1)
            ->whereNull('ms.idTraslado')
            ->whereBetween('ms.fecha', [$inicio, $fin])
            ->whereNotNull('ms.idPrograma')
            ->select([
                'ms.idMovimiento',
                'ms.idPrograma',
                'ms.idPresentacion',
                'ms.fecha',
                'ms.fechaRegistro',
                'ms.idUsuario',
                'ms.idTipoConsumo',
                'ms.cantidad',
                'f.nomFamilia',
            ]);

        if ($excluirIdsMovimiento !== []) {
            $query->whereNotIn('ms.idMovimiento', $excluirIdsMovimiento);
        }

        $porLote = [];
        foreach ($query->get() as $row) {
            if (! FamiliaMaterialSupport::esCajas($row->nomFamilia ?? null)) {
                continue;
            }

            $fecha = is_string($row->fecha) ? $row->fecha : $row->fecha->format('Y-m-d');
            $lote = implode('|', [
                (int) $row->idPrograma,
                (int) $row->idPresentacion,
                $fecha,
                (string) $row->fechaRegistro,
                (int) ($row->idUsuario ?? 0),
                (int) ($row->idTipoConsumo ?? 0),
            ]);

            $cantidad = (float) $row->cantidad;
            $porLote[$lote] = isset($porLote[$lote])
                ? max($porLote[$lote], $cantidad)
                : $cantidad;
        }

        return array_sum($porLote);
    }

    public function idSemProduccionParaFecha(string $fecha): ?int
    {
        $fecha = substr(trim($fecha), 0, 10);
        if ($fecha === '') {
            return null;
        }

        if (self::$mapaFechaSem === null) {
            self::$mapaFechaSem = [];
            SemProduccion::query()
                ->activo()
                ->orderBy('nroOrden')
                ->get(['idSemProduccion', 'fechaInicio'])
                ->each(function (SemProduccion $sem) {
                    if ($sem->fechaInicio === null) {
                        return;
                    }
                    for ($d = 0; $d <= 6; $d++) {
                        $dia = $sem->fechaInicio->copy()->addDays($d)->toDateString();
                        self::$mapaFechaSem[$dia] = (int) $sem->idSemProduccion;
                    }
                });
        }

        return self::$mapaFechaSem[$fecha] ?? null;
    }

    private function formatearCajas(float $valor): string
    {
        return number_format($valor, 0, '.', ',');
    }
}
