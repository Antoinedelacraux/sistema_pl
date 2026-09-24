<?php

namespace App\Models\Programas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TablaComercialDia extends Model
{
    protected $table = 'TablaComercialDia';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'idProgramaGeneral',
        'idSemProduccion',
        'dia',
        'Estado',
    ];

    protected $casts = [
        'dia'    => 'integer',
        'Estado' => 'integer',
    ];

    public function programaGeneral(): BelongsTo
    {
        return $this->belongsTo(ProgramaGeneral::class, 'idProgramaGeneral', 'idProgramaGeneral');
    }

    public function semana(): BelongsTo
    {
        return $this->belongsTo(SemProduccion::class, 'idSemProduccion', 'idSemProduccion');
    }
}
