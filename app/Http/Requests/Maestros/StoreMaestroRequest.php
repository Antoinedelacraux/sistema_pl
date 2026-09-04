<?php

namespace App\Http\Requests\Maestros;

use App\Models\Estado;
use App\Support\MaestrosCatalogo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaestroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->puede('maestros', 'crear');
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

        return [
            'nombre' => [
                'required',
                'string',
                'max:'.$definicion['maxLength'],
                Rule::unique($definicion['tabla'], $definicion['nombre'])
                    ->where(fn ($query) => $query->where('Estado', Estado::ACTIVO)),
            ],
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
