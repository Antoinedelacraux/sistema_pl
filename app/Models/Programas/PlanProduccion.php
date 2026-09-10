<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanProduccion extends Model
{
    use HasEstado;

    protected $table = 'planProduccion';

    protected $primaryKey = 'idPlanProduccion';

    public $timestamps = false;

    protected $fillable = [
        'idProgramaGeneral',
        'idSemProduccion',
        'cantidad',
        'calificacion',
        'nroOrden',
        'Estado',
    ];

    protected $casts = [
        'cantidad' => 'float',
        'nroOrden' => 'integer',
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
