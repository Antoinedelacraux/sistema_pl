<?php

namespace App\Models\Programas;

use App\Models\Concerns\HasEstado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramaCatalogo extends Model
{
    use HasEstado;

    protected $table = 'Programa';

    protected $primaryKey = 'idPrograma';

    public $timestamps = false;

    protected $fillable = ['nomPrograma', 'idProgramaGeneral', 'Estado'];

    public function programaGeneral(): BelongsTo
    {
        return $this->belongsTo(ProgramaGeneral::class, 'idProgramaGeneral', 'idProgramaGeneral');
    }
}
