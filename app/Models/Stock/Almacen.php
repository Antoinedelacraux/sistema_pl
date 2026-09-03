<?php

namespace App\Models\Stock;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;

class Almacen extends Model
{
    use HasEstado;

    protected $table = 'Almacen';

    protected $primaryKey = 'idAlmacen';

    public $timestamps = false;

    protected $fillable = ['codigo', 'nomAlmacen', 'orden', 'Estado'];
}
