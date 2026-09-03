<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermiso
{
    public function handle(Request $request, Closure $next, string $modulo, string $accion = 'ver'): Response
    {
        $usuario = $request->user();

        if (! $usuario || ! $usuario->puede($modulo, $accion)) {
            abort(403, 'No tienes permiso para acceder a este módulo.');
        }

        return $next($request);
    }
}
