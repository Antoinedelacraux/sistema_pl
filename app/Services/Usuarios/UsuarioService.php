<?php

namespace App\Services\Usuarios;

use App\Models\Estado;
use App\Models\Persona;
use App\Models\Usuario;
use App\Services\Auditoria\AuditoriaService;
use App\Services\Permisos\PermisoService;
use App\Support\AuditoriaAccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioService
{
    public function __construct(
        private readonly PermisoService $permisoService,
        private readonly AuditoriaService $auditoria
    ) {}

    public function crear(array $datos, ?Request $request = null): Usuario
    {
        return DB::transaction(function () use ($datos, $request) {
            $persona = Persona::query()->create([
                'Nombres' => $datos['nombres'],
                'Apellidos' => $datos['apellidos'],
                'Correo' => $datos['correo'],
                'idArea' => $datos['id_area'],
                'idCargo' => $datos['id_cargo'],
                'Estado' => $datos['estado'] ?? Estado::ACTIVO,
            ]);

            $cuenta = Usuario::query()->create([
                'idPersona' => $persona->idPersona,
                'idRol' => $datos['id_rol'],
                'Usuario' => $datos['usuario'],
                'Password' => Hash::make($datos['password']),
                'fechaCreacion' => now(),
                'Estado' => $datos['estado'] ?? Estado::ACTIVO,
            ]);

            $this->guardarPermisos($cuenta, $datos, true);

            $cuenta->load(['persona.area', 'persona.cargo', 'rol']);

            $this->auditoria->registrar(
                accion: AuditoriaAccion::USUARIO_CREAR,
                entidad: 'Usuario',
                idEntidad: $cuenta->idUsuario,
                descripcion: 'Cuenta creada: '.$cuenta->Usuario,
                datosNuevos: $this->snapshotUsuario($cuenta),
                request: $request,
            );

            return $cuenta;
        });
    }

    public function actualizar(Usuario $cuenta, array $datos, ?Request $request = null): Usuario
    {
        return DB::transaction(function () use ($cuenta, $datos, $request) {
            $cuenta->load(['persona.area', 'persona.cargo', 'rol']);
            $anterior = $this->snapshotUsuario($cuenta);

            $persona = $cuenta->persona;

            $persona->update([
                'Nombres' => $datos['nombres'],
                'Apellidos' => $datos['apellidos'],
                'Correo' => $datos['correo'],
                'idArea' => $datos['id_area'],
                'idCargo' => $datos['id_cargo'],
                'Estado' => $datos['estado'],
            ]);

            $payload = [
                'idRol' => $datos['id_rol'],
                'Usuario' => $datos['usuario'],
                'Estado' => $datos['estado'],
            ];

            if (! empty($datos['password'])) {
                $payload['Password'] = Hash::make($datos['password']);
            }

            $cuenta->update($payload);
            $this->guardarPermisos($cuenta, $datos, false);

            $actualizado = $cuenta->fresh(['persona.area', 'persona.cargo', 'rol']);

            $this->auditoria->registrar(
                accion: AuditoriaAccion::USUARIO_ACTUALIZAR,
                entidad: 'Usuario',
                idEntidad: $actualizado->idUsuario,
                descripcion: 'Cuenta actualizada: '.$actualizado->Usuario,
                datosAnteriores: $anterior,
                datosNuevos: $this->snapshotUsuario($actualizado),
                request: $request,
            );

            return $actualizado;
        });
    }

    public function desactivar(Usuario $cuenta, ?Request $request = null): Usuario
    {
        return DB::transaction(function () use ($cuenta, $request) {
            $cuenta->load(['persona.area', 'persona.cargo', 'rol']);
            $anterior = $this->snapshotUsuario($cuenta);

            $cuenta->persona->update(['Estado' => Estado::INACTIVO]);
            $cuenta->update(['Estado' => Estado::INACTIVO]);

            $actualizado = $cuenta->fresh(['persona.area', 'persona.cargo', 'rol']);

            $this->auditoria->registrar(
                accion: AuditoriaAccion::USUARIO_DESACTIVAR,
                entidad: 'Usuario',
                idEntidad: $actualizado->idUsuario,
                descripcion: 'Cuenta desactivada: '.$actualizado->Usuario,
                datosAnteriores: $anterior,
                datosNuevos: $this->snapshotUsuario($actualizado),
                request: $request,
            );

            return $actualizado;
        });
    }

    public function activar(Usuario $cuenta, ?Request $request = null): Usuario
    {
        return DB::transaction(function () use ($cuenta, $request) {
            $cuenta->load(['persona.area', 'persona.cargo', 'rol']);
            $anterior = $this->snapshotUsuario($cuenta);

            $cuenta->persona->update(['Estado' => Estado::ACTIVO]);
            $cuenta->update(['Estado' => Estado::ACTIVO]);

            $actualizado = $cuenta->fresh(['persona.area', 'persona.cargo', 'rol']);

            $this->auditoria->registrar(
                accion: AuditoriaAccion::USUARIO_ACTIVAR,
                entidad: 'Usuario',
                idEntidad: $actualizado->idUsuario,
                descripcion: 'Cuenta activada: '.$actualizado->Usuario,
                datosAnteriores: $anterior,
                datosNuevos: $this->snapshotUsuario($actualizado),
                request: $request,
            );

            return $actualizado;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotUsuario(Usuario $cuenta): array
    {
        return [
            'idUsuario' => $cuenta->idUsuario,
            'Usuario' => $cuenta->Usuario,
            'idRol' => $cuenta->idRol,
            'rol' => $cuenta->rol?->Nombre,
            'Estado' => $cuenta->Estado,
            'Nombres' => $cuenta->persona?->Nombres,
            'Apellidos' => $cuenta->persona?->Apellidos,
            'Correo' => $cuenta->persona?->Correo,
            'idArea' => $cuenta->persona?->idArea,
            'area' => $cuenta->persona?->area?->nomArea,
            'idCargo' => $cuenta->persona?->idCargo,
            'cargo' => $cuenta->persona?->cargo?->nomCargo,
        ];
    }

    private function guardarPermisos(Usuario $cuenta, array $datos, bool $usarDefaultRol): void
    {
        if (array_key_exists('permisos', $datos)) {
            $this->permisoService->sincronizar($cuenta, $datos['permisos'] ?? []);

            return;
        }

        if ($usarDefaultRol) {
            $this->permisoService->sincronizarMatriz(
                $cuenta,
                $this->permisoService->matrizPorRol((int) $datos['id_rol'])
            );

            return;
        }

        $this->permisoService->sincronizar($cuenta, []);
    }
}
