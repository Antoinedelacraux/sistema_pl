<?php

namespace App\Services\Auditoria;

use App\Models\AuditoriaLog;
use App\Support\AuditoriaAccion;
use App\Support\GridPagination;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AuditoriaGridService
{
    /**
     * @param  array{
     *   buscar: string,
     *   accion: string,
     *   usuario: string,
     *   fecha_desde: string,
     *   fecha_hasta: string,
     * }  $filtros
     */
    public function listadoPaginado(array $filtros, Request $request): LengthAwarePaginator
    {
        $query = AuditoriaLog::query()
            ->with(['usuario.persona'])
            ->orderByDesc('fechaRegistro')
            ->orderByDesc('idAuditoria');

        if ($filtros['accion'] !== '') {
            $query->where('accion', $filtros['accion']);
        }

        if ($filtros['usuario'] !== '') {
            $query->where('idUsuario', (int) $filtros['usuario']);
        }

        if ($filtros['fecha_desde'] !== '') {
            $query->whereDate('fechaRegistro', '>=', $filtros['fecha_desde']);
        }

        if ($filtros['fecha_hasta'] !== '') {
            $query->whereDate('fechaRegistro', '<=', $filtros['fecha_hasta']);
        }

        if ($filtros['buscar'] !== '') {
            $termino = mb_strtolower($filtros['buscar']);

            $query->where(function ($q) use ($termino) {
                $q->whereRaw('LOWER(descripcion) LIKE ?', ['%'.$termino.'%'])
                    ->orWhereRaw('LOWER(accion) LIKE ?', ['%'.$termino.'%'])
                    ->orWhereRaw('LOWER(ip) LIKE ?', ['%'.$termino.'%'])
                    ->orWhereHas('usuario', function ($usuarioQuery) use ($termino) {
                        $usuarioQuery
                            ->whereRaw('LOWER(Usuario) LIKE ?', ['%'.$termino.'%'])
                            ->orWhereHas('persona', function ($personaQuery) use ($termino) {
                                $personaQuery
                                    ->whereRaw('LOWER(Nombres) LIKE ?', ['%'.$termino.'%'])
                                    ->orWhereRaw('LOWER(Apellidos) LIKE ?', ['%'.$termino.'%'])
                                    ->orWhereRaw('LOWER(Correo) LIKE ?', ['%'.$termino.'%']);
                            });
                    });
            });
        }

        return $query->paginate(GridPagination::PER_PAGE)->withQueryString();
    }

    /**
     * @return array{
     *   eventos_hoy: int,
     *   logins_hoy: int,
     *   fallos_7d: int,
     *   total: int,
     * }
     */
    public function contadores(): array
    {
        $hoy = now()->toDateString();
        $hace7Dias = now()->subDays(7)->startOfDay();

        return [
            'eventos_hoy' => AuditoriaLog::query()->whereDate('fechaRegistro', $hoy)->count(),
            'logins_hoy' => AuditoriaLog::query()
                ->where('accion', AuditoriaAccion::LOGIN)
                ->whereDate('fechaRegistro', $hoy)
                ->count(),
            'fallos_7d' => AuditoriaLog::query()
                ->where('accion', AuditoriaAccion::LOGIN_FALLIDO)
                ->where('fechaRegistro', '>=', $hace7Dias)
                ->count(),
            'total' => AuditoriaLog::query()->count(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function accionesDisponibles(): array
    {
        return AuditoriaAccion::etiquetas();
    }

    /**
     * @return Collection<int, \App\Models\Usuario>
     */
    public function usuariosConActividad(): Collection
    {
        $ids = AuditoriaLog::query()
            ->whereNotNull('idUsuario')
            ->distinct()
            ->pluck('idUsuario');

        return \App\Models\Usuario::query()
            ->with('persona')
            ->whereIn('idUsuario', $ids)
            ->orderBy('Usuario')
            ->get();
    }
}
