<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;

class ProgramaCatalogo extends Model
{
    use HasEstado;

    protected $table = 'Programa';

    protected $primaryKey = 'idPrograma';

    public $timestamps = false;

    protected $fillable = ['nomPrograma', 'Estado'];
}
