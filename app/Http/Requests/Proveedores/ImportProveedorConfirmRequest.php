<?php

namespace App\Http\Requests\Proveedores;

use App\Models\Estado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportProveedorConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->puede('proveedor', 'crear') ?? false;
    }

    public function rules(): array
    {
        return [
            'nom_proveedor' => [
                'required',
                'string',
                'max:100',
                Rule::exists('ProveedorCatalogo', 'nomProveedor')->where('Estado', Estado::ACTIVO),
            ],
            'filas' => ['required', 'array', 'min:1'],
            'filas.*.id_material_pl' => ['required', 'integer', Rule::exists('MaterialProgramaPL', 'idMaterialPL')],
            'filas.*.codigo' => ['required', 'string', 'max:30'],
            'filas.*.nom_material' => ['required', 'string', 'max:255'],
        ];
    }
}
