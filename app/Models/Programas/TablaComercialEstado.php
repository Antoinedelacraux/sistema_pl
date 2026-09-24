<?php

namespace App\Models\Programas;

use Illuminate\Database\Eloquent\Model;

class TablaComercialEstado extends Model
{
    protected $table = 'TablaComercialEstado';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'idProgramaGeneral',
        'idSemProduccion',
        'estado',
    ];

    // Solo "despachado" es manual; el resto es automático por egresos
    public const DESPACHADO = 'despachado';
}
