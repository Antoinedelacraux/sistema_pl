<?php

namespace App\Services\Auditoria;

use App\Models\AuditoriaLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditoriaService
{
    /**
     * @param  array<string, mixed>|null  $datosAnteriores
     * @param  array<string, mixed>|null  $datosNuevos
     */
    public function registrar(
        string $accion,
        ?string $entidad = null,
        ?int $idEntidad = null,
        ?string $descripcion = null,
        ?array $datosAnteriores = null,
        ?array $datosNuevos = null,
        ?int $idUsuario = null,
        ?Request $request = null,
    ): AuditoriaLog {
        $request ??= request();

        return AuditoriaLog::query()->create([
            'idUsuario' => $idUsuario ?? Auth::id(),
            'accion' => $accion,
            'entidad' => $entidad,
            'idEntidad' => $idEntidad,
            'descripcion' => $descripcion,
            'datosAnteriores' => $this->sanitizar($datosAnteriores),
            'datosNuevos' => $this->sanitizar($datosNuevos),
            'ip' => $request?->ip(),
            'userAgent' => $request?->userAgent() ? mb_substr((string) $request->userAgent(), 0, 500) : null,
            'fechaRegistro' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $datos
     * @return array<string, mixed>|null
     */
    private function sanitizar(?array $datos): ?array
    {
        if ($datos === null) {
            return null;
        }

        unset($datos['Password'], $datos['password']);

        return $datos;
    }
}
