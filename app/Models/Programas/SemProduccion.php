<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;

class SemProduccion extends Model
{
    use HasEstado;

    protected $table = 'semProduccion';

    protected $primaryKey = 'idSemProduccion';

    public $timestamps = false;

    protected $fillable = [
        'etiqueta',
        'codigoSemana',
        'fechaInicio',
        'nroOrden',
        'Estado',
    ];

    protected $casts = [
        'fechaInicio' => 'date',
        'nroOrden' => 'integer',
    ];
}
