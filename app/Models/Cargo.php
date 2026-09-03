<?php

namespace App\Models;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cargo extends Model
{
    use HasEstado;

    protected $table = 'Cargo';

    protected $primaryKey = 'idCargo';

    public $timestamps = false;

    protected $fillable = [
        'nomCargo',
        'Estado',
    ];

    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class, 'idCargo', 'idCargo');
    }
}
