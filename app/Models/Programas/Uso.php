<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;

class Uso extends Model
{
    use HasEstado;

    protected $table = 'Uso';

    protected $primaryKey = 'idUso';

    public $timestamps = false;

    protected $fillable = ['nomUso', 'Estado'];
}
