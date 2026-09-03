<?php

namespace App\Models\Stock;

use App\Models\Programas\MaterialProgramaPL;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Traslado extends Model
{
    public const TIPO_ARCHIVO = 'traslado';

    protected $table = 'Traslado';

    protected $primaryKey = 'idTraslado';

    public $timestamps = false;

    protected $fillable = [
        'idMaterialPL',
        'idAlmacenOrigen',
        'idAlmacenDestino',
        'cantidad',
        'fecha',
        'observacion',
        'facturaGuia',
        'factura',
        'guia',
        'archivoOneDriveId',
        'archivoOneDriveUrl',
        'archivoNombre',
        'idUsuario',
        'fechaRegistro',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'fecha' => 'date',
            'fechaRegistro' => 'datetime',
            'estado' => 'integer',
        ];
    }

    public function materialPl(): BelongsTo
    {
        return $this->belongsTo(MaterialProgramaPL::class, 'idMaterialPL', 'idMaterialPL');
    }

    public function almacenOrigen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'idAlmacenOrigen', 'idAlmacen');
    }

    public function almacenDestino(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'idAlmacenDestino', 'idAlmacen');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'idUsuario', 'idUsuario');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoStock::class, 'idTraslado', 'idTraslado');
    }
}
