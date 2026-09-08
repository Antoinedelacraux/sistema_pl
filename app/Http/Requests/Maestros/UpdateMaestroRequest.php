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

    protected function prepareForValidation(): void
    {
        if ($this->has('nombre')) {
            $this->merge([
                'nombre' => trim((string) $this->input('nombre')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tab = MaestrosCatalogo::normalizarTab($this->route('tab'));
        $definicion = MaestrosCatalogo::definicion($tab);
        $id = (int) $this->route('id');

        $rules = [
            'nombre' => [
                'required',
                'string',
                'max:'.$definicion['maxLength'],
                Rule::unique($definicion['tabla'], $definicion['nombre'])
                    ->ignore($id, $definicion['pk']),
            ],
            'estado' => ['required', 'integer', Rule::in([Estado::ACTIVO, Estado::INACTIVO])],
        ];

        if (in_array('CodigoProGeneral', $definicion['extraFields'] ?? [], true)) {
            $rules['CodigoProGeneral'] = [
                'required', 'string', 'max:50',
                Rule::unique('ProgramaGeneral', 'CodigoProGeneral')->ignore($id, 'idProgramaGeneral'),
            ];
        }

        if (in_array('fecha', $definicion['extraFields'] ?? [], true)) {
            $rules['fecha'] = ['nullable', 'date'];
        }

        return $rules;
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
            'CodigoProGeneral' => 'código',
            'fecha' => 'fecha',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'El :attribute ya está registrado.',
        ];
    }
}
