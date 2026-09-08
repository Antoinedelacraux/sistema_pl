<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramaGeneral extends Model
{
    use HasEstado;

    protected $table = 'ProgramaGeneral';

    protected $primaryKey = 'idProgramaGeneral';

    public $timestamps = false;

    protected $fillable = [
        'CodigoProGeneral',
        'nombre',
        'fecha',
        'Estado',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function programas(): HasMany
    {
        return $this->hasMany(ProgramaCatalogo::class, 'idProgramaGeneral', 'idProgramaGeneral');
    }
}
