<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // La foto no viaja aquí dentro: se guarda aparte, en el disco.
            'foto'          => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'foto_eliminar' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Escribe tu nombre.',
            'email.email'   => 'Escribe un correo electrónico válido.',
            'email.unique'  => 'Ese correo ya tiene una cuenta.',
            'foto.image'    => 'El archivo debe ser una imagen.',
            'foto.mimes'    => 'La foto debe ser JPG, PNG o WEBP.',
            'foto.max'      => 'La foto no puede pesar más de 2 MB.',
        ];
    }

    /**
     * Solo estos campos se asignan en masa al usuario; la foto se procesa
     * aparte en el controlador porque no es un valor, es un archivo.
     *
     * @return array<string, mixed>
     */
    public function datosDelPerfil(): array
    {
        return $this->safe()->only(['name', 'email']);
    }
}
