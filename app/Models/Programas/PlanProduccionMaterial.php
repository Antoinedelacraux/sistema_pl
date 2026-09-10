<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanProduccionMaterial extends Model
{
    use HasEstado;

    protected $table = 'planProduccionMaterial';

    protected $primaryKey = 'idPlanProduccionMaterial';

    public $timestamps = false;

    protected $fillable = [
        'idProgramaGeneral',
        'idMaterialPL',
        'idSemProduccion',
        'cantidad',
        'Estado',
    ];

    protected $casts = [
        'cantidad' => 'float',
    ];

    public function programaGeneral(): BelongsTo
    {
        return $this->belongsTo(ProgramaGeneral::class, 'idProgramaGeneral', 'idProgramaGeneral');
    }
}
