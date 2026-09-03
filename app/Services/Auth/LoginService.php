<?php

namespace App\Services\Auth;

use App\Models\Usuario;
use App\Services\Auditoria\AuditoriaService;
use App\Support\AuditoriaAccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginService
{
    public function __construct(
        private readonly AuditoriaService $auditoria
    ) {}

    public function attempt(string $usuario, string $password, Request $request): bool
    {
        $cuenta = Usuario::query()
            ->with(['persona.area', 'persona.cargo', 'rol'])
            ->activo()
            ->where('Usuario', $usuario)
            ->first();

        if (! $cuenta || ! $cuenta->persona?->estaActivo()) {
            $this->auditoria->registrar(
                accion: AuditoriaAccion::LOGIN_FALLIDO,
                descripcion: 'Intento de acceso con cuenta inexistente o inactiva',
                datosNuevos: ['usuario' => $usuario],
                idUsuario: null,
                request: $request,
            );

            return false;
        }

        if (! Hash::check($password, $cuenta->getAuthPassword())) {
            $this->auditoria->registrar(
                accion: AuditoriaAccion::LOGIN_FALLIDO,
                descripcion: 'Contraseña incorrecta',
                datosNuevos: ['usuario' => $usuario],
                idUsuario: $cuenta->idUsuario,
                request: $request,
            );

            return false;
        }

        Auth::login($cuenta);
        session()->regenerate();

        $this->auditoria->registrar(
            accion: AuditoriaAccion::LOGIN,
            entidad: 'Usuario',
            idEntidad: $cuenta->idUsuario,
            descripcion: 'Inicio de sesión: '.$cuenta->Usuario,
            idUsuario: $cuenta->idUsuario,
            request: $request,
        );

        return true;
    }

    public function logout(?Request $request = null): void
    {
        $usuario = Auth::user();

        if ($usuario instanceof Usuario) {
            $this->auditoria->registrar(
                accion: AuditoriaAccion::LOGOUT,
                entidad: 'Usuario',
                idEntidad: $usuario->idUsuario,
                descripcion: 'Cierre de sesión: '.$usuario->Usuario,
                idUsuario: $usuario->idUsuario,
                request: $request,
            );
        }

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }
}
