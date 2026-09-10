<?php

namespace App\Http\Requests\PlanProduccion;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanProduccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->puede('plan_produccion', 'editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'cantidades' => ['required', 'array'],
            'cantidades.*' => ['array'],
            'cantidades.*.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'materiales' => ['nullable', 'array'],
            'materiales.*' => ['array'],
            'materiales.*.*' => ['array'],
            'materiales.*.*.*' => ['nullable', 'numeric', 'min:0', 'max:99999999.9999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'cantidades' => 'cajas del plan',
        ];
    }
}
