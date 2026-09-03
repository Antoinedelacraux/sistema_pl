<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;

class Variedad extends Model
{
    use HasEstado;

    protected $table = 'Variedad';

    protected $primaryKey = 'idVariedad';

    public $timestamps = false;

    protected $fillable = ['nomVariedad', 'Estado'];
}
