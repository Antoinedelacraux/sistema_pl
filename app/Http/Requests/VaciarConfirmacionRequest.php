<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Confirmación escrita para acciones destructivas "Vaciar" (receta, MRP, plan de producción).
 * El permiso (acción "eliminar" del módulo) lo valida el middleware de la ruta.
 */
class VaciarConfirmacionRequest extends FormRequest
{
    public const TEXTO = 'VACIAR';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirmacion' => ['required', 'string', 'in:'.self::TEXTO],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmacion.required' => 'Escriba '.self::TEXTO.' para confirmar.',
            'confirmacion.in' => 'Confirmación incorrecta: escriba '.self::TEXTO.' en mayúsculas.',
        ];
    }
}
