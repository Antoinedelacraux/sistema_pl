<?php

namespace App\Models\Concerns;

use App\Models\Estado;
use Illuminate\Database\Eloquent\Builder;

trait HasEstado
{
    public function scopeActivo(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('Estado'), Estado::ACTIVO);
    }

    public function estaActivo(): bool
    {
        return (int) $this->Estado === Estado::ACTIVO;
    }
}
