<?php

namespace App\Models;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use HasEstado;

    protected $table = 'Area';

    protected $primaryKey = 'idArea';

    public $timestamps = false;

    protected $fillable = [
        'nomArea',
        'Estado',
    ];

    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class, 'idArea', 'idArea');
    }
}
