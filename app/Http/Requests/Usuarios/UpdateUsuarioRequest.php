<?php

namespace App\Http\Requests\Usuarios;

use App\Models\Estado;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esAdministrador() ?? false;
    }

    public function rules(): array
    {
        /** @var Usuario $cuenta */
        $cuenta = $this->route('usuario');

        return [
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'correo' => [
                'required',
                'email',
                'max:150',
                Rule::unique('Persona', 'Correo')->ignore($cuenta->idPersona, 'idPersona'),
            ],
            'id_area' => ['required', 'integer', Rule::exists('Area', 'idArea')],
            'id_cargo' => ['required', 'integer', Rule::exists('Cargo', 'idCargo')],
            'id_rol' => ['required', 'integer', Rule::exists('Rol', 'idRol')],
            'usuario' => [
                'required',
                'string',
                'max:150',
                Rule::unique('Usuario', 'Usuario')->ignore($cuenta->idUsuario, 'idUsuario'),
            ],
            'password' => ['nullable', 'string', 'min:6', 'max:255'],
            'estado' => ['required', 'integer', Rule::in([Estado::ACTIVO, Estado::INACTIVO])],
            'permisos' => ['nullable', 'array'],
            'permisos.*' => ['nullable', 'array'],
            'permisos.*.ver' => ['nullable', 'boolean'],
            'permisos.*.crear' => ['nullable', 'boolean'],
            'permisos.*.editar' => ['nullable', 'boolean'],
            'permisos.*.eliminar' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'correo.unique' => 'Ese correo ya está registrado.',
            'usuario.unique' => 'Ese usuario ya existe.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombres' => 'nombres',
            'apellidos' => 'apellidos',
            'correo' => 'correo',
            'id_area' => 'área',
            'id_cargo' => 'cargo',
            'id_rol' => 'rol',
            'usuario' => 'usuario',
            'password' => 'contraseña',
            'estado' => 'estado',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var Usuario $cuenta */
            $cuenta = $this->route('usuario');

            if (
                (int) $this->input('estado') === Estado::INACTIVO
                && $cuenta->is($this->user())
            ) {
                $validator->errors()->add('estado', 'No puedes desactivar tu propia cuenta.');
            }
        });
    }
}
