<?php

namespace App\Http\Requests\Catalogo;

use App\Http\Requests\Concerns\ReglasDeImagen;
use App\Rules\DelNegocio;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta y edición de Supplier (H-03). Las reglas `unique`/`exists` van
 * acotadas al negocio activo (H-F10 / H-F11). El permiso de crear o
 * editar lo exige el middleware de la ruta.
 */
class GuardarSupplierRequest extends FormRequest
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
            'name'         => ['required', 'string', 'max:150'],
            'tax_id'       => ['nullable', 'string', 'max:30', DelNegocio::unico('suppliers', 'tax_id')->ignore($this->route('supplier')?->id)],
            'phone'        => ['nullable', 'string', 'max:20', 'regex:/^[0-9+()\s-]+$/'],
            'email'        => ['nullable', 'email', 'max:150'],
            'address'      => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:100'],
        ] + $this->reglasImagen();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'El proveedor necesita un nombre o razón social.',
            'tax_id.unique' => 'Ese NIT ya está registrado en otro proveedor.',
            'email.email'   => 'Escribe un correo electrónico válido.',
            'phone.regex'   => 'El teléfono solo admite números, espacios y los signos + ( ) -',
        ] + $this->mensajesImagen();
    }
}
