<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;

class CantidadCaja extends Model
{
    use HasEstado;

    protected $table = 'CantidadCaja';

    protected $primaryKey = 'idCantidadCaja';

    public $timestamps = false;

    protected $fillable = ['cantidad', 'Estado'];

    protected $casts = [
        'cantidad' => 'decimal:6',
    ];
}
