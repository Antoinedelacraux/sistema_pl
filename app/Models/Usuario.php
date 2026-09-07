<?php

namespace App\Models;

use App\Models\Concerns\HasEstado;
use App\Models\Rol;
use App\Support\PermisoAccion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    use HasEstado;

    protected $table = 'Usuario';

    protected $primaryKey = 'idUsuario';

    public $timestamps = false;

    protected $fillable = [
        'idPersona',
        'idRol',
        'Usuario',
        'Password',
        'fechaCreacion',
        'Estado',
    ];

    protected $hidden = [
        'Password',
    ];

    /** @var array<string, UsuarioPermiso>|null */
    private ?array $permisosPorModuloCache = null;

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'idPersona', 'idPersona');
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'idRol', 'idRol');
    }

    public function permisos(): HasMany
    {
        return $this->hasMany(UsuarioPermiso::class, 'idUsuario', 'idUsuario');
    }

    public function getAuthPassword(): string
    {
        return $this->Password;
    }

    public function getAuthIdentifierName(): string
    {
        return $this->primaryKey;
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public function esAdministrador(): bool
    {
        return $this->rol?->Nombre === Rol::ADMINISTRADOR && $this->estaActivo();
    }

    public function puede(string $modulo, string $accion): bool
    {
        if (! in_array($accion, PermisoAccion::todas(), true)) {
            return false;
        }

        $permiso = $this->permisoPorModulo($modulo);

        if (! $permiso) {
            return false;
        }

        return match ($accion) {
            PermisoAccion::VER => (bool) $permiso->puede_ver,
            PermisoAccion::CREAR => (bool) $permiso->puede_crear,
            PermisoAccion::EDITAR => (bool) $permiso->puede_editar,
            PermisoAccion::ELIMINAR => (bool) $permiso->puede_eliminar,
            default => false,
        };
    }

    public function puedeVer(string $modulo): bool
    {
        return $this->puede($modulo, PermisoAccion::VER);
    }

    /**
     * Carga todos los permisos del usuario una sola vez por request
     * (sidebar + middleware + flags de vista).
     */
    private function permisoPorModulo(string $modulo): ?UsuarioPermiso
    {
        if ($this->permisosPorModuloCache === null) {
            $this->permisosPorModuloCache = [];

            $permisos = $this->relationLoaded('permisos')
                ? $this->permisos
                : $this->permisos()->with(['modulo:idModulo,codigo'])->get();

            if ($permisos->isNotEmpty() && ! $permisos->first()->relationLoaded('modulo')) {
                $permisos->load(['modulo:idModulo,codigo']);
            }

            foreach ($permisos as $permiso) {
                $codigo = $permiso->modulo?->codigo;
                if (is_string($codigo) && $codigo !== '') {
                    $this->permisosPorModuloCache[$codigo] = $permiso;
                }
            }
        }

        return $this->permisosPorModuloCache[$modulo] ?? null;
    }
}