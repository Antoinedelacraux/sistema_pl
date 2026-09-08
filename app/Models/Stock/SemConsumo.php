<?php

namespace App\Models\Stock;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;

class SemConsumo extends Model
{
    use HasEstado;

    protected $table = 'semConsumo';

    protected $primaryKey = 'idSemConsumo';

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
