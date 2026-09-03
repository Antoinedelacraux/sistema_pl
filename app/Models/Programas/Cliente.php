<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasEstado;

    protected $table = 'Cliente';

    protected $primaryKey = 'idCliente';

    public $timestamps = false;

    protected $fillable = ['nomCliente', 'Estado'];
}
