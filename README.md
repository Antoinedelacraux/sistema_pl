# system_pl

Sistema de planeamiento y stock (Laravel 11).

Módulos: receta (programas), plan de producción, MRP, proveedores, registro (ingresos/egresos/traslados), inventario, resumen, maestros, usuarios y auditoría.

## Base de datos

- **Esquema versionado:** `database/schema/esquema_base.sql` (tablas, índices, FKs y vistas `vw_*` con los nombres exactos que usa el código).
- **Migración baseline:** `database/migrations/2026_09_29_000000_esquema_base.php` ejecuta ese SQL solo si la tabla `Usuario` no existe.
  - BD nueva: `php artisan migrate` y luego `php artisan db:seed` (roles, módulos, almacenes y un administrador; la contraseña temporal se muestra en consola o se toma de `SEED_ADMIN_PASSWORD`).
  - BD existente (local/producción): no ejecutar la baseline; registrarla como aplicada (`produccion_registrar_baseline.sql`).
- Cambios de esquema futuros: nuevas migraciones en `database/migrations` (no scripts sueltos).

## Comandos de mantenimiento (`php artisan pl:*`)

Reemplazan a los scripts `database/NN_*.php` (copias originales en `database/legacy/`). Los destructivos piden confirmación o `--force`.

| Comando | Antes | Uso |
|---------|-------|-----|
| `pl:sincronizar-receta-excel [archivo]` | 55 | Import receta desde Excel + orden |
| `pl:comparar-receta-excel [archivo]` | 54 | Comparar receta vs Excel (solo lectura) |
| `pl:reordenar-receta` | 56 | Renumerar orden de filas activas |
| `pl:reparar-programa-general-receta [archivo]` | 47 | Reparar Prog. General de la receta desde Excel |
| `pl:vaciar-ingresos-consumos` | 57 | Borrar ingresos y consumos (no traslados) antes de stock inicial |
| `pl:importar-stock-safco [archivo]` | 32 | Cargar stock inicial desde `Stock.xlsx` → SAFCO |
| `pl:ingreso-stock-sin-cantidad [archivo] --cantidad=50000` | 34 | Ingreso por defecto para filas sin cantidad |
| `pl:vaciar-stock-almacenes` | 31 | Borrar movimientos/traslados de SFC/SF/FC |
| `pl:vaciar-receta` | 30 | Vaciar la receta |
| `pl:deduplicar-materiales` | 35 | Fusionar materiales duplicados por CodigoPL |
| `pl:verificar-stock-indices` | 50 | Verificar vistas de stock e índices |

## Logs

Dos capas:

1. **Técnicos (archivos)** — Laravel/Monolog en `storage/logs/` (requiere permiso de escritura en el servidor).
   - Por defecto: `LOG_STACK=daily` → `laravel-YYYY-MM-DD.log` (errores, excepciones, `Log::warning`, etc.).
   - Opcional: `Log::channel('app')` → `app-YYYY-MM-DD.log`.
   - Solo desarrollo: `LOG_QUERIES=true` → `queries.log`.
   - Producción recomendado: `LOG_LEVEL=warning`, `APP_DEBUG=false`.

2. **Auditoría (negocio)** — tabla `AuditoriaLog` + pantalla **Auditoría** (admin): login, fallos de acceso, cambios en receta, registro, usuarios, etc.

Tras cambiar `.env` de logs en producción: `php artisan config:cache`.

## Seguridad (OWASP-oriented)

| Control | Implementación |
|---------|----------------|
| **A01 Acceso** | `auth`, `usuario.activo`, `permiso:modulo,accion`, `admin`; tarjeta usuario con reglas en controlador |
| **A02 Cripto** | HTTPS forzado detrás de proxy, cookies `httpOnly`, HSTS en HTTPS, contraseñas bcrypt + regla mín. 8 con letras y números |
| **A03 Inyección** | Eloquent/bindings, validación Form Request, uploads con `mimes` y tamaño |
| **A04 Diseño** | Sin API pública; reglas de negocio en servidor |
| **A05 Config** | `APP_DEBUG` off fuera de local, headers en `SecurityHeaders`, docroot `public/` |
| **A07 Auth** | Rate limit login (IP + usuario), auditoría de fallos, regeneración de sesión |
| **A09 Logging** | `storage/logs` + `AuditoriaLog` |

**Rate limits** (`config/security.php`, `.env` `RATE_LIMIT_*`):

- `login` — intentos de acceso
- `web` — 120 req/min por usuario (rutas autenticadas)
- `imports` — Excel/XML y imports pesados
- `destructive` — vaciar, anular, borrar filas

Respuesta **429** si se excede. Opcional en prod: WAF/Cloudflare delante del login.

Tras cambiar variables de seguridad: `php artisan config:cache`.
