<?php

namespace App\Http\Requests\Proveedores;

use App\Models\Estado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportProveedorPreviewRequest extends FormRequest
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
            'archivo_excel' => [
                'required',
                'file',
                'mimes:xlsx,xlsm,xls',
                'max:'.(int) config('power_automate.max_upload_kb', 10240),
            ],
        ];
    }
}
