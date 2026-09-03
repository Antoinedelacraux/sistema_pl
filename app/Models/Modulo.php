<?php

namespace App\Models;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modulo extends Model
{
    use HasEstado;

    protected $table = 'Modulo';

    protected $primaryKey = 'idModulo';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'orden',
        'Estado',
    ];

    public function permisos(): HasMany
    {
        return $this->hasMany(UsuarioPermiso::class, 'idModulo', 'idModulo');
    }
}
