<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardaImagen;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class CustomerController extends Controller implements HasMiddleware
{
    use GuardaImagen;

    /** Tipos de documento aceptados en Colombia. */
    public const DOCUMENTOS = [
        'CC'  => 'Cédula de ciudadanía',
        'TI'  => 'Tarjeta de identidad',
        'CE'  => 'Cédula de extranjería',
        'NIT' => 'NIT (empresa)',
        'PAS' => 'Pasaporte',
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:clientes.ver',      only: ['index', 'show']),
            new Middleware('permission:clientes.crear',    only: ['create', 'store']),
            new Middleware('permission:clientes.editar',   only: ['edit', 'update']),
            new Middleware('permission:clientes.eliminar', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->search($q)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('customers.index', compact('customers', 'q'));
    }

    public function create()
    {
        return view('customers.create', ['documentos' => self::DOCUMENTOS]);
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $data['image_url'] = $this->guardarImagen($request, 'clientes');

        unset($data['imagen']);

        $cliente = Customer::create($data);

        return redirect()->route('customers.index')
            ->with('success', 'Cliente «' . $cliente->full_name . '» registrado.');
    }

    public function show(Customer $customer)
    {
        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', [
            'customer'   => $customer,
            'documentos' => self::DOCUMENTOS,
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $data  = $this->validar($request, $customer);
        $nueva = $this->guardarImagen($request, 'clientes', $customer->image_url);

        if ($nueva !== false) {
            $data['image_url'] = $nueva;
        }

        unset($data['imagen']);

        $customer->update($data);

        return redirect()->route('customers.index')
            ->with('success', 'Cliente «' . $customer->full_name . '» actualizado.');
    }

    public function destroy(Customer $customer)
    {
        $nombre = $customer->full_name;
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'Cliente «' . $nombre . '» eliminado.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'first_name'      => ['required', 'string', 'max:100'],
            'last_name'       => ['nullable', 'string', 'max:100'],
            'email'           => ['nullable', 'email', 'max:150', Rule::unique('customers', 'email')->ignore($customer?->id)],
            'phone'           => ['nullable', 'string', 'max:20', 'regex:/^[0-9+()\s-]+$/'],
            'document_type'   => ['nullable', Rule::in(array_keys(self::DOCUMENTOS))],
            'document_number' => ['nullable', 'string', 'max:30', Rule::unique('customers', 'document_number')->ignore($customer?->id)],
            'address'         => ['nullable', 'string', 'max:255'],
        ] + $this->reglasImagen(), [
            'first_name.required'      => 'El nombre es obligatorio.',
            'email.email'              => 'Escribe un correo electrónico válido.',
            'email.unique'             => 'Ese correo ya está registrado en otro cliente.',
            'phone.regex'              => 'El teléfono solo admite números, espacios y los signos + ( ) -',
            'document_number.unique'   => 'Ese número de documento ya está registrado.',
        ] + $this->mensajesImagen());
    }
}
