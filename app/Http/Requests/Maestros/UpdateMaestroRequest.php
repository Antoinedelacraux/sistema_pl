<?php

namespace App\Http\Requests\Maestros;

use App\Models\Estado;
use App\Support\MaestrosCatalogo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaestroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->puede('maestros', 'editar');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tab = MaestrosCatalogo::normalizarTab($this->route('tab'));
        $definicion = MaestrosCatalogo::definicion($tab);
        $id = (int) $this->route('id');

        return [
            'nombre' => [
                'required',
                'string',
                'max:'.$definicion['maxLength'],
                Rule::unique($definicion['tabla'], $definicion['nombre'])->ignore($id, $definicion['pk']),
            ],
            'estado' => ['required', 'integer', Rule::in([Estado::ACTIVO, Estado::INACTIVO])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $tab = MaestrosCatalogo::normalizarTab($this->route('tab'));
        $label = MaestrosCatalogo::definicion($tab)['label'];

        return [
            'nombre' => 'nombre de '.$label,
            'estado' => 'estado',
        ];
    }
}
