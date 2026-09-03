<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;

class MaterialProgramaPL extends Model
{
    use HasEstado;

    protected $table = 'MaterialProgramaPL';

    protected $primaryKey = 'idMaterialPL';

    public $timestamps = false;

    protected $fillable = ['CodigoPL', 'nomMaterial', 'fechaRegistro', 'Estado'];

    protected $casts = [
        'fechaRegistro' => 'date',
    ];
}
