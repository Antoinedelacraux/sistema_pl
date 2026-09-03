<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;

class Mercado extends Model
{
    use HasEstado;

    protected $table = 'Mercado';

    protected $primaryKey = 'idMercado';

    public $timestamps = false;

    protected $fillable = ['nomMercado', 'Estado'];
}
