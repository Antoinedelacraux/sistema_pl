<?php

namespace App\Models\Proveedores;

use App\Models\Programas\Familia;
use App\Models\Programas\MaterialProgramaPL;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Proveedor extends Model
{
    protected $table = 'Proveedor';

    protected $primaryKey = 'idProveedor';

    public $timestamps = false;

    protected $fillable = [
        'nroFila',
        'idFamilia',
        'idMaterialPL',
        'nomProveedor',
        'codigo',
        'nomMaterial',
        'fecha',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'estado' => 'integer',
        ];
    }

    public function familia(): BelongsTo
    {
        return $this->belongsTo(Familia::class, 'idFamilia', 'idFamilia');
    }

    public function materialPl(): BelongsTo
    {
        return $this->belongsTo(MaterialProgramaPL::class, 'idMaterialPL', 'idMaterialPL');
    }
}
