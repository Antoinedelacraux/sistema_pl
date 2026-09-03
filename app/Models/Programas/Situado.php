<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;

class Situado extends Model
{
    use HasEstado;

    protected $table = 'Situado';

    protected $primaryKey = 'idSituado';

    public $timestamps = false;

    protected $fillable = ['nomSituado', 'Estado'];
}
