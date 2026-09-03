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

        $permiso = $this->permisos()
            ->whereHas('modulo', fn ($q) => $q->where('codigo', $modulo))
            ->first();

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
}



