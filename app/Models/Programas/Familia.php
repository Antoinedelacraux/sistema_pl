<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;

class Familia extends Model
{
    use HasEstado;

    protected $table = 'Familia';

    protected $primaryKey = 'idFamilia';

    public $timestamps = false;

    protected $fillable = ['nomFamilia', 'Estado'];
}
