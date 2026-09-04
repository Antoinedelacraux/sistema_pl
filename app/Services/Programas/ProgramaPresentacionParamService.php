<?php

namespace App\Services\Programas;

use App\Models\Estado;
use App\Models\Programas\ProgramaPresentacionParam;
use App\Support\GridPagination;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProgramaPresentacionParamService
{
    public function listarPaginado(array $filtros, Request $request): LengthAwarePaginator
    {
        $query = DB::table('ProgramaFila as pf')
            ->join('Programa as prog', 'prog.idPrograma', '=', 'pf.idPrograma')
            ->join('Presentacion as pres', 'pres.idPresentacion', '=', 'pf.idPresentacion')
            ->leftJoin('ProgramaPresentacionParam as param', function ($join) {
                $join->on('param.idPrograma', '=', 'pf.idPrograma')
                    ->on('param.idPresentacion', '=', 'pf.idPresentacion')
                    ->where('param.Estado', '=', Estado::ACTIVO);
            })
            ->where('pf.Estado', Estado::ACTIVO)
            ->selectRaw('
                pf.idPrograma as id_programa,
                pf.idPresentacion as id_presentacion,
                prog.nomPrograma as nom_programa,
                pres.nomPresentacion as nom_presentacion,
                MAX(param.idProgramaPresentacionParam) as id_param,
                MAX(param.pallets) as pallets,
                MAX(param.conformacion) as conformacion
            ')
            ->groupBy('pf.idPrograma', 'pf.idPresentacion', 'prog.nomPrograma', 'pres.nomPresentacion')
            ->orderBy('prog.nomPrograma')
            ->orderBy('pres.nomPresentacion');

        if (! empty($filtros['programa'])) {
            $query->where('pf.idPrograma', (int) $filtros['programa']);
        }

        if (! empty($filtros['presentacion'])) {
            $query->where('pf.idPresentacion', (int) $filtros['presentacion']);
        }

        if (! empty($filtros['buscar'])) {
            $buscar = '%'.addcslashes((string) $filtros['buscar'], '%_\\').'%';
            $query->where(function ($q) use ($buscar) {
                $q->where('prog.nomPrograma', 'like', $buscar)
                    ->orWhere('pres.nomPresentacion', 'like', $buscar);
            });
        }

        return $query
            ->paginate(GridPagination::PER_PAGE)
            ->withQueryString();
    }

    public function guardar(int $idPrograma, int $idPresentacion, float $pallets, float $conformacion): ProgramaPresentacionParam
    {
        return ProgramaPresentacionParam::query()->updateOrCreate(
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
    }
}
