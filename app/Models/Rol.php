<?php

namespace App\Models;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    use HasEstado;

    public const ADMINISTRADOR = 'Administrador';

    protected $table = 'Rol';

    protected $primaryKey = 'idRol';

    public $timestamps = false;

    protected $fillable = [
        'Nombre',
        'Estado',
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'idRol', 'idRol');
    }
}
