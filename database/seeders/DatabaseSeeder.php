<?php

namespace Database\Seeders;

use App\Models\Estado;
use App\Models\Rol;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Datos mínimos para una BD nueva creada con la migración baseline.
 * Idempotente: se puede correr varias veces (php artisan db:seed).
 *
 * Usuario administrador inicial:
 *   - Usuario: SEED_ADMIN_USUARIO (por defecto admin@talsa.com.pe)
 *   - Contraseña: SEED_ADMIN_PASSWORD; si no existe se genera una aleatoria y se muestra en consola.
 *   Si el usuario ya existe no se toca su contraseña.
 */
class DatabaseSeeder extends Seeder
{
    /** @var list<array{0: int, 1: string, 2: string, 3: int}> idModulo, codigo, nombre, orden */
    private const MODULOS = [
        [2, 'usuarios', 'Usuarios', 2],
        [4, 'materiales', 'Materiales', 4],
        [5, 'familias', 'Familias', 5],
        [6, 'registro', 'Registro', 9],
        [7, 'programas', 'Receta', 3],
        [14, 'proveedor', 'Proveedor', 6],
        [27, 'inventario', 'Inventario', 7],
        [28, 'auditoria', 'Auditoría', 10],
        [31, 'maestros', 'Crud', 5],
        [37, 'inicio', 'Inicio', 1],
        [38, 'mrp', 'Plan MRP', 4],
        [39, 'plan_produccion', 'Plan de Producción', 5],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ([[1, Rol::ADMINISTRADOR], [2, 'Operario'], [3, 'Usuario']] as [$id, $nombre]) {
                DB::table('Rol')->updateOrInsert(['idRol' => $id], ['Nombre' => $nombre, 'Estado' => Estado::ACTIVO]);
            }

            foreach (self::MODULOS as [$id, $codigo, $nombre, $orden]) {
                DB::table('Modulo')->updateOrInsert(
                    ['idModulo' => $id],
                    ['codigo' => $codigo, 'nombre' => $nombre, 'orden' => $orden, 'Estado' => Estado::ACTIVO]
                );
            }

            DB::table('Area')->updateOrInsert(['idArea' => 1], ['nomArea' => 'Planeamiento e Información', 'Estado' => Estado::ACTIVO]);
            DB::table('Cargo')->updateOrInsert(['idCargo' => 1], ['nomCargo' => 'Asistente', 'Estado' => Estado::ACTIVO]);

            foreach ([[1, 'SFC', 'SAFCO', 1], [2, 'SF', 'SUN FRUIT', 2], [3, 'FC', 'FRUCHINCHA', 3]] as [$id, $codigo, $nombre, $orden]) {
                DB::table('Almacen')->updateOrInsert(
                    ['idAlmacen' => $id],
                    ['codigo' => $codigo, 'nomAlmacen' => $nombre, 'orden' => $orden, 'Estado' => Estado::ACTIVO]
                );
            }

            foreach ([[1, 'Dentro'], [2, 'Fuera']] as [$id, $nombre]) {
                DB::table('Situado')->updateOrInsert(['idSituado' => $id], ['nomSituado' => $nombre, 'Estado' => Estado::ACTIVO]);
            }

            foreach ([[1, 'Común'], [2, 'Alternativo'], [4, 'Oficial']] as [$id, $nombre]) {
                DB::table('Uso')->updateOrInsert(['idUso' => $id], ['nomUso' => $nombre, 'Estado' => Estado::ACTIVO]);
            }

            $this->crearAdministrador();
        });
    }

    private function crearAdministrador(): void
    {
        $usuario = (string) (env('SEED_ADMIN_USUARIO') ?: 'admin@talsa.com.pe');

        $existente = DB::table('Usuario')->where('Usuario', $usuario)->first();
        if ($existente) {
            $idUsuario = (int) $existente->idUsuario;
            $this->command?->info("Administrador {$usuario} ya existe; no se cambia la contraseña.");
        } else {
            $password = (string) (env('SEED_ADMIN_PASSWORD') ?: Str::password(16, symbols: false));

            $idPersona = DB::table('Persona')->insertGetId([
                'Nombres' => 'Administrador',
                'Apellidos' => 'Sistema',
                'Correo' => $usuario,
                'idArea' => 1,
                'idCargo' => 1,
                'Estado' => Estado::ACTIVO,
            ]);

            $idUsuario = DB::table('Usuario')->insertGetId([
                'idPersona' => $idPersona,
                'idRol' => 1,
                'Usuario' => $usuario,
                'Password' => Hash::make($password),
                'fechaCreacion' => now(),
                'Estado' => Estado::ACTIVO,
            ]);

            if (! env('SEED_ADMIN_PASSWORD')) {
                $this->command?->warn("Administrador creado: {$usuario} / contraseña temporal: {$password} (cámbiela al ingresar).");
            } else {
                $this->command?->info("Administrador creado: {$usuario}.");
            }
        }

        foreach (self::MODULOS as [$idModulo]) {
            DB::table('UsuarioPermiso')->updateOrInsert(
                ['idUsuario' => $idUsuario, 'idModulo' => $idModulo],
                ['puede_ver' => 1, 'puede_crear' => 1, 'puede_editar' => 1, 'puede_eliminar' => 1]
            );
        }
    }
}
