<?php

namespace App\Http\Requests\Mrp;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMrpPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->puede('mrp', 'editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'cantidades' => ['required', 'array'],
            'cantidades.*' => ['array'],
            'cantidades.*.*' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    public function attributes(): array
    {
        return [
            'cantidades' => 'cantidades del plan',
        ];
    }
}
