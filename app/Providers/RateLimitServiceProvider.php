<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $usuario = mb_strtolower(trim((string) $request->input('usuario', '')));
            $porIp = (int) config('security.rate_limit.login_per_minute', 5);

            return [
                Limit::perMinute($porIp)->by($request->ip()),
                Limit::perMinute(max($porIp * 3, 15))->by($request->ip().'|'.$usuario),
            ];
        });

        RateLimiter::for('web', function (Request $request) {
            $key = $request->user()?->idUsuario ?? $request->ip();

            return Limit::perMinute((int) config('security.rate_limit.web_per_minute', 120))
                ->by((string) $key);
        });

        RateLimiter::for('imports', function (Request $request) {
            $key = $request->user()?->idUsuario ?? $request->ip();

            return Limit::perMinute((int) config('security.rate_limit.imports_per_minute', 10))
                ->by('import|'.$key);
        });

        RateLimiter::for('destructive', function (Request $request) {
            $key = $request->user()?->idUsuario ?? $request->ip();

            return Limit::perMinute((int) config('security.rate_limit.destructive_per_minute', 8))
                ->by('destructive|'.$key);
        });
    }
}
