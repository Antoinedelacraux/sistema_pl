<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;

class TipoConsumo extends Model
{
    use HasEstado;

    protected $table = 'TipoConsumo';

    protected $primaryKey = 'idTipoConsumo';

    public $timestamps = false;

    protected $fillable = ['nomTipoConsumo', 'Estado'];
}
