<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Idempotencia de altas de movimientos (ingreso / consumo / traslado): cola offline y envío
 * normal con conexión (doble clic o reintento del mismo formulario).
 *
 * El navegador genera un UUID por registro encolado o por formulario abierto (client_request_id)
 * y lo reenvía en cada reintento; si no viene, el alta se procesa como siempre (sin deduplicar). Aquí se reserva la clave usuario + tipo + uuid con Cache::add (atómico en los
 * stores database/redis), de modo que un mismo registro no se inserte dos veces aunque la
 * respuesta se pierda y el cliente reintente.
 *
 * Limitación (sin cambio de esquema): la garantía dura lo que el TTL de la caché y depende de
 * que la caché sea compartida y persistente (CACHE_STORE=database o redis). La solución
 * definitiva es una columna con índice único; ver DEPLOY.md, sección "Pendientes".
 */
class IdempotenciaMovimiento
{
    public const ESTADO_NUEVO = 'nuevo';

    public const ESTADO_HECHO = 'hecho';

    public const ESTADO_EN_PROCESO = 'en_proceso';

    /** Reserva mientras se procesa; si el proceso muere, la clave expira y se puede reintentar. */
    private const TTL_PROCESANDO_SEGUNDOS = 600;

    /** Cuánto se recuerda un registro ya creado (la cola offline puede reintentar días después). */
    private const TTL_HECHO_SEGUNDOS = 1_209_600; // 14 días

    private const PATRON_ID = '/^[A-Za-z0-9][A-Za-z0-9_-]{15,63}$/';

    /**
     * Clave de caché para la petición, o null si no trae un client_request_id válido o no hay usuario.
     */
    public function clave(Request $request, string $tipo): ?string
    {
        $id = trim((string) ($request->input('client_request_id') ?: $request->header('X-Idempotency-Key', '')));
        $idUsuario = $request->user()?->getAuthIdentifier();

        if ($id === '' || $idUsuario === null || ! preg_match(self::PATRON_ID, $id)) {
            return null;
        }

        return 'mov-idem:'.$tipo.':'.$idUsuario.':'.strtolower($id);
    }

    /**
     * @return array{estado: string, respuesta: array<string, mixed>|null}
     */
    public function iniciar(?string $clave): array
    {
        if ($clave === null) {
            return ['estado' => self::ESTADO_NUEVO, 'respuesta' => null];
        }

        try {
            if (Cache::add($clave, ['estado' => 'procesando'], self::TTL_PROCESANDO_SEGUNDOS)) {
                return ['estado' => self::ESTADO_NUEVO, 'respuesta' => null];
            }

            $valor = Cache::get($clave);
        } catch (\Throwable $exception) {
            // Si la caché falla no se bloquea el registro (comportamiento anterior), pero se deja traza.
            Log::warning('IdempotenciaMovimiento: caché no disponible; se procesa sin deduplicar.', [
                'error' => $exception->getMessage(),
            ]);

            return ['estado' => self::ESTADO_NUEVO, 'respuesta' => null];
        }

        if (is_array($valor) && ($valor['estado'] ?? null) === 'hecho') {
            return ['estado' => self::ESTADO_HECHO, 'respuesta' => $valor['respuesta'] ?? []];
        }

        return ['estado' => self::ESTADO_EN_PROCESO, 'respuesta' => null];
    }

    /**
     * @param  array<string, mixed>  $respuesta
     */
    public function completar(?string $clave, array $respuesta): void
    {
        if ($clave === null) {
            return;
        }

        try {
            Cache::put($clave, ['estado' => 'hecho', 'respuesta' => $respuesta], self::TTL_HECHO_SEGUNDOS);
        } catch (\Throwable $exception) {
            Log::warning('IdempotenciaMovimiento: no se pudo marcar como completado.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Libera la reserva cuando el alta falló, para que el cliente pueda reintentar.
     */
    public function liberar(?string $clave): void
    {
        if ($clave === null) {
            return;
        }

        try {
            Cache::forget($clave);
        } catch (\Throwable) {
            // La reserva expira sola (TTL_PROCESANDO_SEGUNDOS).
        }
    }
}
