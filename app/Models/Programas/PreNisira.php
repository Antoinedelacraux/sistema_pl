<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;

class PreNisira extends Model
{
    use HasEstado;

    protected $table = 'PreNisira';

    protected $primaryKey = 'idPreNisira';

    public $timestamps = false;

    protected $fillable = ['nomNisira', 'Estado'];
}
