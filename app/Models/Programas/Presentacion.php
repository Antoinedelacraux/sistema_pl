<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;

class Presentacion extends Model
{
    use HasEstado;

    protected $table = 'Presentacion';

    protected $primaryKey = 'idPresentacion';

    public $timestamps = false;

    protected $fillable = ['nomPresentacion', 'Estado'];
}
