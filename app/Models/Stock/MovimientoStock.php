<?php

namespace App\Models\Stock;

use App\Models\Programas\MaterialProgramaPL;
use App\Models\Programas\Presentacion;
use App\Models\Programas\ProgramaCatalogo;
use App\Models\Programas\TipoConsumo;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoStock extends Model
{
    public const TIPO_INGRESO = 'ingreso';

    public const TIPO_EGRESO = 'egreso';

    protected $table = 'MovimientoStock';

    protected $primaryKey = 'idMovimiento';

    public $timestamps = false;

    protected $fillable = [
        'tipo',
        'idAlmacen',
        'idMaterialPL',
        'cantidad',
        'fecha',
        'observacion',
        'idTipoConsumo',
        'idPrograma',
        'idPresentacion',
        'facturaGuia',
        'factura',
        'guia',
        'archivoOneDriveId',
        'archivoOneDriveUrl',
        'archivoNombre',
        'idTraslado',
        'idUsuario',
        'fechaRegistro',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            // BD: DECIMAL(18,6). Se guarda la cantidad exacta; los 2 decimales son solo visuales.
            'cantidad' => 'decimal:6',
            'fecha' => 'date',
            'fechaRegistro' => 'datetime',
            'estado' => 'integer',
        ];
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'idAlmacen', 'idAlmacen');
    }

    public function materialPl(): BelongsTo
    {
        return $this->belongsTo(MaterialProgramaPL::class, 'idMaterialPL', 'idMaterialPL');
    }

    public function tipoConsumo(): BelongsTo
    {
        return $this->belongsTo(TipoConsumo::class, 'idTipoConsumo', 'idTipoConsumo');
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(ProgramaCatalogo::class, 'idPrograma', 'idPrograma');
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(Presentacion::class, 'idPresentacion', 'idPresentacion');
    }

    public function traslado(): BelongsTo
    {
        return $this->belongsTo(Traslado::class, 'idTraslado', 'idTraslado');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'idUsuario', 'idUsuario');
    }
}
