<?php

namespace App\Services\Programas;

use App\Models\Estado;
use App\Models\Programas\ProgramaPresentacionParam;
use App\Support\GridPagination;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProgramaPresentacionParamService
{
    private const CACHE_TTL = 600;

    public function listarPaginado(array $filtros, Request $request): LengthAwarePaginator
    {
        $perPage = GridPagination::PER_PAGE;
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * $perPage;

        $version = (int) Cache::get('programa.presentacion_params.cache_version', 0);
        $cacheKey = 'programa.presentacion_params.v'.$version.'.'
            .md5(json_encode($filtros)).'.p'.$page;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filtros, $request, $perPage, $page, $offset) {
            return $this->listarPaginadoSinCache($filtros, $request, $perPage, $page, $offset);
        });
    }

    public function guardar(int $idPrograma, int $idPresentacion, float $pallets, float $conformacion): ProgramaPresentacionParam
    {
        $param = ProgramaPresentacionParam::query()->updateOrCreate(
            [
                'idPrograma' => $idPrograma,
                'idPresentacion' => $idPresentacion,
            ],
            [
                'pallets' => $pallets,
                'conformacion' => $conformacion,
                'Estado' => Estado::ACTIVO,
            ]
        );

        $this->olvidarCacheListado();

        return $param;
    }

    public function olvidarCacheListado(): void
    {
        $version = (int) Cache::get('programa.presentacion_params.cache_version', 0);
        Cache::forever('programa.presentacion_params.cache_version', $version + 1);
    }

    private function listarPaginadoSinCache(
        array $filtros,
        Request $request,
        int $perPage,
        int $page,
        int $offset
    ): LengthAwarePaginator {
        $query = $this->queryCombinaciones($filtros);

        $filas = (clone $query)
            ->selectRaw('COUNT(*) OVER() AS total_filas')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $total = (int) ($filas->first()->total_filas ?? 0);

        return new LengthAwarePaginator(
            $filas,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    /**
     * Combinaciones distintas programa+presentación (desde receta), enriquecidas con params.
     */
    private function queryCombinaciones(array $filtros): Builder
    {
        $combo = DB::table('ProgramaFila as pf')
            ->join('Programa as prog_f', 'prog_f.idPrograma', '=', 'pf.idPrograma')
            ->leftJoin('ProgramaGeneral as pg_f', 'pg_f.idProgramaGeneral', '=', 'prog_f.idProgramaGeneral')
            ->where('pf.Estado', Estado::ACTIVO)
            ->select('pf.idPrograma', 'pf.idPresentacion')
            ->distinct();

        $this->aplicarFiltrosCombo($combo, $filtros, 'pf', 'prog_f', 'pg_f');

        return DB::query()
            ->fromSub($combo, 'combo')
            ->join('Programa as prog', 'prog.idPrograma', '=', 'combo.idPrograma')
            ->join('Presentacion as pres', 'pres.idPresentacion', '=', 'combo.idPresentacion')
            ->leftJoin('ProgramaGeneral as pg', 'pg.idProgramaGeneral', '=', 'prog.idProgramaGeneral')
            ->leftJoin('ProgramaPresentacionParam as param', function ($join) {
                $join->on('param.idPrograma', '=', 'combo.idPrograma')
                    ->on('param.idPresentacion', '=', 'combo.idPresentacion')
                    ->where('param.Estado', '=', Estado::ACTIVO);
            })
            ->select([
                'combo.idPrograma as id_programa',
                'combo.idPresentacion as id_presentacion',
                'pg.nombre as nom_programa_general',
                'pg.CodigoProGeneral as codigo_programa_general',
                'prog.nomPrograma as nom_programa',
                'pres.nomPresentacion as nom_presentacion',
                'param.idProgramaPresentacionParam as id_param',
                'param.pallets as pallets',
                'param.conformacion as conformacion',
            ])
            ->orderBy('pg.nombre')
            ->orderBy('prog.nomPrograma')
            ->orderBy('pres.nomPresentacion');
    }

    private function aplicarFiltrosCombo(
        Builder $query,
        array $filtros,
        string $pf,
        string $prog,
        string $pg
    ): void {
        if (! empty($filtros['programa_general'])) {
            $query->where("{$prog}.idProgramaGeneral", (int) $filtros['programa_general']);
        }

        if (! empty($filtros['programa'])) {
            $query->where("{$pf}.idPrograma", (int) $filtros['programa']);
        }

        if (! empty($filtros['presentacion'])) {
            $query->where("{$pf}.idPresentacion", (int) $filtros['presentacion']);
        }

        if (! empty($filtros['buscar'])) {
            $buscar = '%'.addcslashes((string) $filtros['buscar'], '%_\\').'%';
            $query->join('Presentacion as pres_f', 'pres_f.idPresentacion', '=', "{$pf}.idPresentacion")
                ->where(function ($q) use ($buscar, $prog, $pg) {
                    $q->where("{$prog}.nomPrograma", 'like', $buscar)
                        ->orWhere('pres_f.nomPresentacion', 'like', $buscar)
                        ->orWhere("{$pg}.nombre", 'like', $buscar)
                        ->orWhere("{$pg}.CodigoProGeneral", 'like', $buscar);
                });
        }
    }
}
