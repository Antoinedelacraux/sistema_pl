<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsuarioPermiso extends Model
{
    protected $table = 'UsuarioPermiso';

    protected $primaryKey = 'idUsuarioPermiso';

    public $timestamps = false;

    protected $fillable = [
        'idUsuario',
        'idModulo',
        'puede_ver',
        'puede_crear',
        'puede_editar',
        'puede_eliminar',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'idUsuario', 'idUsuario');
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'idModulo', 'idModulo');
    }
}
