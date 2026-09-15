<?php

namespace App\Http\Requests\Proveedores;

use App\Models\Estado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProveedorRequest extends FormRequest
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
            'codigo' => ['required', 'string', 'max:30'],
            'nom_material' => ['required', 'string', 'max:255'],
            'id_material_pl' => ['required', 'integer', Rule::exists('MaterialProgramaPL', 'idMaterialPL')],
        ];
    }

    public function attributes(): array
    {
        return [
            'nom_proveedor' => 'proveedor',
            'codigo' => 'código',
            'nom_material' => 'material del proveedor',
            'id_material_pl' => 'código PL de planeamiento',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('proveedor.index');
    }
}
