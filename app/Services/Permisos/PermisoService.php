<?php

namespace App\Services\Permisos;

use App\Models\Modulo;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\UsuarioPermiso;
use App\Support\PermisoAccion;

class PermisoService
{
    public function modulosActivos()
    {
        return Modulo::query()->activo()->orderBy('orden')->get();
    }

    public function matrizPorRol(int $idRol): array
    {
        $modulos = $this->modulosActivos();
        $config = config('permisos.roles.'.$idRol);

        if ($config === 'all') {
            return $this->matrizCompleta($modulos);
        }

        $matriz = [];
        foreach ($modulos as $modulo) {
            $defaults = $config[$modulo->codigo] ?? [];
            $matriz[$modulo->codigo] = $this->filaDesdeDefaults($defaults);
        }

        return $matriz;
    }

    public function matrizParaUsuario(?Usuario $cuenta): array
    {
        $modulos = $this->modulosActivos();
        $matriz = [];

        if (! $cuenta) {
            return $matriz;
        }

        $guardados = $cuenta->permisos()
            ->with('modulo')
            ->get()
            ->keyBy(fn (UsuarioPermiso $p) => $p->modulo->codigo);

        foreach ($modulos as $modulo) {
            $permiso = $guardados->get($modulo->codigo);
            $matriz[$modulo->codigo] = [
                PermisoAccion::VER => (bool) ($permiso?->puede_ver),
                PermisoAccion::CREAR => (bool) ($permiso?->puede_crear),
                PermisoAccion::EDITAR => (bool) ($permiso?->puede_editar),
                PermisoAccion::ELIMINAR => (bool) ($permiso?->puede_eliminar),
            ];
        }

        return $matriz;
    }

    public function matrizDesdeRequest(array $input): array
    {
        $matriz = [];
        foreach ($this->modulosActivos() as $modulo) {
            $fila = $input[$modulo->codigo] ?? [];
            $matriz[$modulo->codigo] = [
                PermisoAccion::VER => ! empty($fila[PermisoAccion::VER]),
                PermisoAccion::CREAR => ! empty($fila[PermisoAccion::CREAR]),
                PermisoAccion::EDITAR => ! empty($fila[PermisoAccion::EDITAR]),
                PermisoAccion::ELIMINAR => ! empty($fila[PermisoAccion::ELIMINAR]),
            ];
        }

        return $matriz;
    }

    public function sincronizar(Usuario $cuenta, array $permisosInput): void
    {
        $this->sincronizarMatriz($cuenta, $this->matrizDesdeRequest($permisosInput));
    }

    public function sincronizarMatriz(Usuario $cuenta, array $matriz): void
    {
        $modulos = $this->modulosActivos()->keyBy('codigo');

        foreach ($matriz as $codigo => $acciones) {
            $modulo = $modulos->get($codigo);
            if (! $modulo) {
                continue;
            }

            UsuarioPermiso::query()->updateOrCreate(
                [
                    'idUsuario' => $cuenta->idUsuario,
                    'idModulo' => $modulo->idModulo,
                ],
                [
                    'puede_ver' => $acciones[PermisoAccion::VER] ? 1 : 0,
                    'puede_crear' => $acciones[PermisoAccion::CREAR] ? 1 : 0,
                    'puede_editar' => $acciones[PermisoAccion::EDITAR] ? 1 : 0,
                    'puede_eliminar' => $acciones[PermisoAccion::ELIMINAR] ? 1 : 0,
                ]
            );
        }
    }

    public function defaultsPorRolJson(): string
    {
        $roles = Rol::query()->activo()->orderBy('idRol')->get();
        $payload = [];

        foreach ($roles as $rol) {
            $payload[$rol->idRol] = $this->matrizPorRol((int) $rol->idRol);
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    private function matrizCompleta($modulos): array
    {
        $matriz = [];
        foreach ($modulos as $modulo) {
            $matriz[$modulo->codigo] = [
                PermisoAccion::VER => true,
                PermisoAccion::CREAR => true,
                PermisoAccion::EDITAR => true,
                PermisoAccion::ELIMINAR => true,
            ];
        }

        return $matriz;
    }

    private function filaDesdeDefaults(array $defaults): array
    {
        $fila = [];
        foreach (PermisoAccion::todas() as $accion) {
            $fila[$accion] = ! empty($defaults[$accion]);
        }

        return $fila;
    }
}
