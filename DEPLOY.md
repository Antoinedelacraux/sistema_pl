# Despliegue en producción (liviano)

Guía para publicar `system_pl` (Laravel 11, PHP 8.3) en el servidor Windows detrás de **Cloudflare Tunnel** (`cloudflared` local).

> Ejecutar todos los comandos desde la carpeta del proyecto. No ejecutar migraciones sin revisar (ver README, sección Base de datos).

## 1. Requisitos de PHP

- PHP 8.2+ (probado con 8.3) con extensiones: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`, **`gd`** (necesaria para `phpoffice/phpspreadsheet` y para comprimir imágenes de receta a WebP/JPEG).
  - Si `gd` no está habilitada, `composer install` falla por plataforma y las imágenes de receta se guardan sin comprimir (solo si su MIME real es JPEG/PNG/WebP/GIF). Habilitar en `php.ini`: `extension=gd`.
- **OPcache** activado (ver sección 4).

## 2. Instalar / actualizar código

```bash
# Dependencias SIN paquetes de desarrollo (no se instala laravel-debugbar, pint ni collision)
composer install --no-dev --optimize-autoloader --no-interaction

# Primera vez: crear .env a partir de la plantilla y generar la clave
copy .env.example .env        # (Windows)  /  cp .env.example .env  (Linux)
php artisan key:generate

# Enlace público para imágenes de receta (storage/app/public -> public/storage), una sola vez
php artisan storage:link
```

## 3. Cachés de Laravel (cada despliegue)

```bash
php artisan optimize:clear     # limpia cachés anteriores
php artisan optimize           # equivale a config:cache + route:cache + view:cache + event:cache
```

O por separado:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

- Con `config:cache` activo, **cualquier cambio en `.env` requiere volver a ejecutar `php artisan config:cache`** (o `optimize`).
- Si se usa la cola (`QUEUE_CONNECTION=database`), reiniciar los workers: `php artisan queue:restart`.

## 4. OPcache recomendado (php.ini)

```ini
; Windows: zend_extension=opcache   (Linux: normalmente ya viene habilitado)
zend_extension=opcache
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=192
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=60
opcache.save_comments=1
; Opcional (máximo rendimiento): validate_timestamps=0 y reiniciar PHP/servidor en cada despliegue.
```

Verificar con `php -i | findstr opcache` (Windows) o `php -i | grep opcache` (Linux), y reiniciar el servicio web / PHP-FPM / `php artisan serve` tras cambiar `php.ini`.

## 5. Variables de `.env` de producción

Ver `.env.example` (valores por defecto ya pensados para producción). Imprescindibles:

| Variable | Valor producción | Motivo |
|---|---|---|
| `APP_ENV` | `production` | Desactiva modo debug y Debugbar (AppServiceProvider) |
| `APP_DEBUG` | `false` | Sin stack traces ni datos de entorno en errores |
| `APP_KEY` | generada (`key:generate`) | Cifrado de sesión/cookies |
| `APP_URL` | `https://<dominio>` | URLs absolutas correctas |
| `TRUSTED_PROXIES` | `127.0.0.1,::1` | Solo `cloudflared` local puede fijar `X-Forwarded-*`; evita falsear IP y evadir el rate limit |
| `TRUSTED_PROXIES_CLOUDFLARE` | `false` | `true` solo si Cloudflare conecta directo al servidor (sin túnel) |
| `LOG_CHANNEL` / `LOG_STACK` | `daily` | Un archivo por día en `storage/logs` |
| `LOG_DAILY_DAYS` | `14` | Retención de 14 días |
| `LOG_LEVEL` | `warning` | Evita logs gigantes de nivel debug/info |
| `LOG_QUERIES` | `false` | No registrar SQL en producción |
| `DEBUGBAR_ENABLED` | `false` | Defensa extra (Debugbar ni siquiera se instala con `--no-dev`) |
| `SESSION_SECURE_COOKIE` | `true` | Cookie de sesión solo por HTTPS |
| `CACHE_STORE` | `database` o `redis` | Caché compartida y persistente: rate limit e idempotencia de la cola offline |
| `SESSION_DRIVER` | `database` | Sesiones persistentes |

## 6. Limpieza de `storage` (solo documentación; revisar antes de borrar)

- **`storage/debugbar/`**: archivos JSON que Debugbar genera en desarrollo. En producción no deberían existir (Debugbar no se instala). Si existen, se pueden eliminar con seguridad cuando la app no esté en modo desarrollo:
  - Windows: `Remove-Item storage\debugbar\*.json`
  - Linux: `rm -f storage/debugbar/*.json`
- **`storage/logs/`**: con `LOG_CHANNEL=daily` y `LOG_DAILY_DAYS=14` Laravel rota y borra solo los `laravel-YYYY-MM-DD.log` antiguos. Revisar y archivar/borrar manualmente archivos sueltos antiguos (`laravel.log` de un solo archivo, `queries.log`, `performance-report*.json`).
- **Vistas compiladas**: `php artisan view:clear` (se regeneran con `view:cache`).

## 7. Verificación rápida tras desplegar

```bash
php artisan about              # entorno, debug=OFF, cachés en CACHED
php artisan route:list --except-vendor
curl -I https://<dominio>/up   # debe responder 200
```

## 8. Seguridad aplicada en esta rama (resumen)

- XSS DOM: vista previa de importación de proveedores y errores de la cola offline insertan texto (`textContent`), no HTML.
- Proxies de confianza configurables (`config/trustedproxy.php`) en lugar de `trustProxies(at: '*')`.
- Rate limit de login adicional **por usuario** (independiente de IP): `RATE_LIMIT_LOGIN_USER` por minuto y `RATE_LIMIT_LOGIN_USER_HOUR` por hora.
- Imágenes de receta: se valida el MIME real y la firma binaria; la extensión se deriva de una lista blanca (jpg/png/webp/gif).
- Cola offline de Registro: cada registro lleva un `client_request_id` (UUID) y el `idUsuario`; solo se sincroniza con la sesión del mismo usuario y el servidor deduplica con `Cache::add` (usuario + tipo + uuid, 14 días).
- Debugbar forzado a desactivado fuera de `local`/`testing`.

## 9. Pendientes (requieren migración o cambios en `MovimientoService`)

### 9.1 Idempotencia definitiva de ingresos/consumos

La deduplicación actual vive en caché (TTL 14 días). Es suficiente para reintentos de red, pero no es una garantía permanente (si se limpia la caché o se usa `CACHE_STORE=array/file` sin compartir, un reintento muy tardío podría duplicar).

Propuesta (migración nueva, revisar nombres reales de tabla/columnas en `database/schema/esquema_base.sql`):

```php
// database/migrations/2026_10_xx_000000_movimiento_client_request_id.php
Schema::table('MovimientoStock', function (Blueprint $table) {
    $table->uuid('clientRequestId')->nullable()->after('idUsuario');
    // Un mismo uuid de cliente solo puede crear movimientos una vez por usuario.
    // Si un alta crea varias líneas (varias filas), usar en su lugar una tabla de cabecera
    // o una tabla aparte "MovimientoSolicitud(idUsuario, clientRequestId, tipo, creado_en)" con
    // UNIQUE(idUsuario, clientRequestId, tipo) e insertarla dentro de la misma transacción.
    $table->unique(['idUsuario', 'clientRequestId'], 'ux_movimiento_usuario_client_request');
});
```

Como un ingreso/consumo puede crear **varias filas** de `MovimientoStock` (una por línea), lo más limpio es la tabla `MovimientoSolicitud` con `UNIQUE(idUsuario, clientRequestId, tipo)`:

1. `MovimientoService::crearIngreso/crearEgreso` recibe `?string $clientRequestId`.
2. Dentro de su `DB::transaction`, antes de insertar líneas: `INSERT INTO MovimientoSolicitud (...)`. Si viola el índice único → el alta ya existe: hacer rollback y devolver "duplicado" (el controlador responde `ok: true, duplicado: true`).
3. Mantener la capa de caché actual como atajo (evita trabajo y bloquea peticiones concurrentes).

No se implementó porque requiere migración y editar `app/Services/Stock/MovimientoService.php` (fuera del alcance acordado).

### 9.2 Envíos online (sin cola)

El formulario normal de ingreso/consumo (con conexión) no envía `client_request_id`, por lo que un doble clic o reenvío del navegador podría duplicar. Se puede añadir un campo oculto con UUID por renderizado una vez revisado `registro.js` (si reutiliza el formulario sin recargar, debe regenerar el UUID tras cada alta exitosa).

### 9.3 Laravel 11 fuera de soporte de seguridad

`composer audit` sigue reportando avisos de `laravel/framework` cuyo rango corregido empieza en 12.x (CRLF en regla `email`, Signed URL path confusion, XSS en página de debug). Laravel 11 ya no recibe esos parches. Mitigaciones actuales: `APP_DEBUG=false` (página de debug); la app no usa URLs firmadas (`signed`/`temporarySignedRoute`), y las reglas `email` de usuarios (`StoreUsuarioRequest`/`UpdateUsuarioRequest`) rechazan saltos de línea con `not_regex:/[\r\n]/`. Planificar la migración a Laravel 12.
