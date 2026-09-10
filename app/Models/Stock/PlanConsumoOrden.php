<?php

namespace App\Models\Stock;

use App\Models\Programas\MaterialProgramaPL;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanConsumoOrden extends Model
{
    protected $table = 'planConsumoOrden';

    protected $primaryKey = 'idMaterialPL';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'idMaterialPL',
        'nroOrden',
    ];

    protected $casts = [
        'nroOrden' => 'integer',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(MaterialProgramaPL::class, 'idMaterialPL', 'idMaterialPL');
    }
}
