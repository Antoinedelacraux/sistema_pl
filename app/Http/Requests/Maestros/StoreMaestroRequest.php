<?php

namespace App\Http\Requests\Maestros;

use App\Support\MaestrosCatalogo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaestroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->puede('maestros', 'crear');
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
                Rule::unique($definicion['tabla'], $definicion['nombre']),
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
}
