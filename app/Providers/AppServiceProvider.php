<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Nunca mostrar stack traces ni .env si el host no es desarrollo.
        if (! $this->app->environment(['local', 'testing'])) {
            $this->app['config']->set('app.debug', false);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePublicUrl();

        if (! $this->shouldLogQueries()) {
            return;
        }

        DB::listen(function (QueryExecuted $query): void {
            Log::channel('queries')->debug($this->formatQueryLog($query));
        });
    }

    /**
     * Evita mixed content (HTTPS página + HTTP CSS) detrás de Cloudflare Tunnel.
     */
    private function configurePublicUrl(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $request = request();
        $forwarded = strtolower((string) $request->headers->get('X-Forwarded-Proto', ''));
        $host = strtolower($request->getHost());

        if ($request->secure() || $forwarded === 'https' || str_contains($host, 'trycloudflare.com') || str_contains($host, 'loca.lt')) {
            URL::forceScheme('https');
        }
    }

    private function shouldLogQueries(): bool
    {
        if (! filter_var(env('LOG_QUERIES', false), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        return $this->app->environment('local')
            || (bool) config('app.debug');
    }

    private function formatQueryLog(QueryExecuted $query): string
    {
        $sql = $this->interpolateBindings($query->sql, $query->bindings);

        return sprintf(
            '[%.2f ms] %s',
            $query->time,
            $sql
        );
    }

    /**
     * @param  array<int, mixed>  $bindings
     */
    private function interpolateBindings(string $sql, array $bindings): string
    {
        foreach ($bindings as $binding) {
            if ($binding === null) {
                $value = 'NULL';
            } elseif (is_bool($binding)) {
                $value = $binding ? '1' : '0';
            } elseif (is_numeric($binding)) {
                $value = (string) $binding;
            } else {
                $value = "'".str_replace("'", "''", (string) $binding)."'";
            }

            $sql = preg_replace('/\?/', $value, $sql, 1) ?? $sql;
        }

        return preg_replace('/\s+/', ' ', trim($sql)) ?? trim($sql);
    }
}