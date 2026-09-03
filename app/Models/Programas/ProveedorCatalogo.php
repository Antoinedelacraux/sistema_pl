<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;

class ProveedorCatalogo extends Model
{
    use HasEstado;

    protected $table = 'ProveedorCatalogo';

    protected $primaryKey = 'idProveedorCatalogo';

    public $timestamps = false;

    protected $fillable = ['nomProveedor', 'Estado'];
}
