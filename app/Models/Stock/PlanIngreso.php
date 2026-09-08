<?php

namespace App\Models\Stock;

use App\Models\Programas\MaterialProgramaPL;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanIngreso extends Model
{
    protected $table = 'planIngreso';

    protected $primaryKey = 'idPlanIngreso';

    public $timestamps = false;

    protected $fillable = [
        'idMaterialPL',
        'idSemConsumo',
        'cantidad',
        'Estado',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(MaterialProgramaPL::class, 'idMaterialPL', 'idMaterialPL');
    }

    public function semConsumo(): BelongsTo
    {
        return $this->belongsTo(SemConsumo::class, 'idSemConsumo', 'idSemConsumo');
    }
}
