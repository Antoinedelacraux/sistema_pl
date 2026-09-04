<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramaPresentacionParam extends Model
{
    use HasEstado;

    protected $table = 'ProgramaPresentacionParam';

    protected $primaryKey = 'idProgramaPresentacionParam';

    public $timestamps = false;

    protected $fillable = [
        'idPrograma',
        'idPresentacion',
        'pallets',
        'conformacion',
        'Estado',
    ];

    protected $casts = [
        'pallets' => 'decimal:2',
        'conformacion' => 'decimal:2',
    ];

    public function programa(): BelongsTo
    {
        return $this->belongsTo(ProgramaCatalogo::class, 'idPrograma', 'idPrograma');
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(Presentacion::class, 'idPresentacion', 'idPresentacion');
    }
}
