<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaLog extends Model
{
    protected $table = 'AuditoriaLog';

    protected $primaryKey = 'idAuditoria';

    public $timestamps = false;

    protected $fillable = [
        'idUsuario',
        'accion',
        'entidad',
        'idEntidad',
        'descripcion',
        'datosAnteriores',
        'datosNuevos',
        'ip',
        'userAgent',
        'fechaRegistro',
    ];

    protected function casts(): array
    {
        return [
            'datosAnteriores' => 'array',
            'datosNuevos' => 'array',
            'fechaRegistro' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'idUsuario', 'idUsuario');
    }
}
