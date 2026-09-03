<?php

namespace App\Models;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Persona extends Model
{
    use HasEstado;

    protected $table = 'Persona';

    protected $primaryKey = 'idPersona';

    public $timestamps = false;

    protected $fillable = [
        'Nombres',
        'Apellidos',
        'Correo',
        'idArea',
        'idCargo',
        'Estado',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'idArea', 'idArea');
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'idCargo', 'idCargo');
    }

    public function usuario(): HasOne
    {
        return $this->hasOne(Usuario::class, 'idPersona', 'idPersona');
    }

    public function nombreCompleto(): string
    {
        return trim("{$this->Nombres} {$this->Apellidos}");
    }
}
