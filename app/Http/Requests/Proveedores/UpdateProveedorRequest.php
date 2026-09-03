<?php

namespace App\Http\Requests\Proveedores;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->puede('proveedor', 'editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'id_familia' => ['nullable', 'integer', Rule::exists('Familia', 'idFamilia')],
            'proveedores' => ['nullable', 'array'],
            'proveedores.*.nombre' => ['required', 'string', 'max:50'],
            'proveedores.*.codigo' => ['nullable', 'string', 'max:30'],
            'proveedores.*.nom_material' => ['nullable', 'string', 'max:255'],
            'proveedores.*.eliminar' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'id_familia' => 'familia',
            'proveedores.*.nombre' => 'proveedor',
            'proveedores.*.codigo' => 'código',
            'proveedores.*.nom_material' => 'material',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('proveedor.index');
    }
}
