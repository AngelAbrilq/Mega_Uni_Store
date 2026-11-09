<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Controllers\CustomerController;
use App\Http\Requests\Concerns\ReglasDeImagen;
use App\Rules\DelNegocio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de Customer (H-03). Las reglas `unique`/`exists` van
 * acotadas al negocio activo (H-F10 / H-F11). El permiso de crear o
 * editar lo exige el middleware de la ruta.
 */
class GuardarCustomerRequest extends FormRequest
{
    use ReglasDeImagen;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['nullable', 'string', 'max:100'],
            'email'           => ['nullable', 'email', 'max:150', DelNegocio::unico('customers', 'email')->ignore($this->route('customer')?->id)],
            'phone'           => ['nullable', 'string', 'max:20', 'regex:/^[0-9+()\s-]+$/'],
            'document_type'   => ['nullable', Rule::in(array_keys(CustomerController::DOCUMENTOS))],
            'document_number' => ['nullable', 'string', 'max:30', DelNegocio::unico('customers', 'document_number')->ignore($this->route('customer')?->id)],
            'address'         => ['nullable', 'string', 'max:255'],
        ] + $this->reglasImagen();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'first_name.required'      => 'El nombre es obligatorio.',
            'email.email'              => 'Escribe un correo electrónico válido.',
            'email.unique'             => 'Ese correo ya está registrado en otro cliente.',
            'phone.regex'              => 'El teléfono solo admite números, espacios y los signos + ( ) -',
            'document_number.unique'   => 'Ese número de documento ya está registrado.',
        ] + $this->mensajesImagen();
    }
}
