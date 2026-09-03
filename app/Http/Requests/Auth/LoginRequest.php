<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'usuario' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'usuario.required' => 'Ingresa tu usuario.',
            'password.required' => 'Ingresa tu contraseña.',
        ];
    }

    public function attributes(): array
    {
        return [
            'usuario' => 'usuario',
            'password' => 'contraseña',
        ];
    }
}
