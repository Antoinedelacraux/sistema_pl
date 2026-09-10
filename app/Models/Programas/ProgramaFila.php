<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramaFila extends Model
{
    use HasEstado;

    protected $table = 'ProgramaFila';

    protected $primaryKey = 'idProgramaFila';

    public $timestamps = false;

    protected $fillable = [
        'nroFila',
        'idMercado',
        'idCliente',
        'idVariedad',
        'idPrograma',
        'idProgramaGeneral',
        'idPresentacion',
        'idMaterialPL',
        'idCantidadCaja',
        'idUso',
        'idSituado',
        'idFamilia',
        'imagen',
        'Estado',
    ];

    public function imagenUrl(): ?string
    {
        if ($this->imagen === null || $this->imagen === '') {
            return null;
        }

        return app(\App\Services\Programas\ProgramaImagenService::class)->url($this->imagen);
    }

    public function mercado(): BelongsTo
    {
        return $this->belongsTo(Mercado::class, 'idMercado', 'idMercado');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'idCliente', 'idCliente');
    }

    public function variedad(): BelongsTo
    {
        return $this->belongsTo(Variedad::class, 'idVariedad', 'idVariedad');
    }

    public function programa(): BelongsTo
    {
        return $this->belongsTo(ProgramaCatalogo::class, 'idPrograma', 'idPrograma');
    }

    public function programaGeneral(): BelongsTo
    {
        return $this->belongsTo(ProgramaGeneral::class, 'idProgramaGeneral', 'idProgramaGeneral');
    }

    public function presentacion(): BelongsTo
    {
        return $this->belongsTo(Presentacion::class, 'idPresentacion', 'idPresentacion');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(MaterialProgramaPL::class, 'idMaterialPL', 'idMaterialPL');
    }

    public function cantidadCaja(): BelongsTo
    {
        return $this->belongsTo(CantidadCaja::class, 'idCantidadCaja', 'idCantidadCaja');
    }

    public function uso(): BelongsTo
    {
        return $this->belongsTo(Uso::class, 'idUso', 'idUso');
    }

    public function situado(): BelongsTo
    {
        return $this->belongsTo(Situado::class, 'idSituado', 'idSituado');
    }

    public function familia(): BelongsTo
    {
        return $this->belongsTo(Familia::class, 'idFamilia', 'idFamilia');
    }
}
