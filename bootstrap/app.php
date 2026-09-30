<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Cloudflare Tunnel / reverse proxies (X-Forwarded-Proto, Host)
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('inicio'));
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdministrador::class,
            'permiso' => \App\Http\Middleware\EnsurePermiso::class,
            'usuario.activo' => \App\Http\Middleware\EnsureUsuarioActivo::class,
        ]);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->context(function () {
            if (app()->runningInConsole()) {
                return [];
            }

            return [
                'user_id' => auth()->id(),
                'url' => request()->fullUrl(),
                'method' => request()->method(),
            ];
        });
    })->create();