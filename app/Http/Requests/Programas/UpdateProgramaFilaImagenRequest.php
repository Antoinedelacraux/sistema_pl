<?php

namespace App\Http\Requests\Programas;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProgramaFilaImagenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->puede('programas', 'editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'imagen' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
            'eliminar_imagen' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'imagen' => 'imagen',
            'eliminar_imagen' => 'quitar imagen',
        ];
    }

    protected function getRedirectUrl(): string
    {
        session()->flash('receta_editar_imagen', $this->route('idProgramaFila'));

        return route('programas.index', array_filter([
            'mercado' => $this->input('mercado'),
            'cliente' => $this->input('cliente'),
            'familia' => $this->input('familia'),
            'programa' => $this->input('programa'),
            'presentacion' => $this->input('presentacion'),
            'buscar' => $this->input('buscar'),
        ], fn ($valor) => filled($valor)));
    }
}
