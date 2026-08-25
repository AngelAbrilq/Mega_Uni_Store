<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GuardaImagen;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class SupplierController extends Controller implements HasMiddleware
{
    use GuardaImagen;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:proveedores.ver',      only: ['index', 'show']),
            new Middleware('permission:proveedores.crear',    only: ['create', 'store']),
            new Middleware('permission:proveedores.editar',   only: ['edit', 'update']),
            new Middleware('permission:proveedores.eliminar', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $suppliers = Supplier::query()
            ->withCount('products')
            ->search($q)
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers', 'q'));
    }

    public function create()
    {
        return view('suppliers.create');
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $data['is_active'] = $request->boolean('is_active');
        $data['image_url'] = $this->guardarImagen($request, 'proveedores');

        unset($data['imagen']);

        $proveedor = Supplier::create($data);

        return redirect()->route('suppliers.index')
            ->with('success', 'Proveedor «' . $proveedor->name . '» registrado.');
    }

    public function show(Supplier $supplier)
    {
        $supplier->loadCount('products');
        $supplier->load(['products' => fn ($q) => $q->orderBy('name')->take(10)]);

        return view('suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $data = $this->validar($request, $supplier);
        $data['is_active'] = $request->boolean('is_active');

        $nueva = $this->guardarImagen($request, 'proveedores', $supplier->image_url);

        if ($nueva !== false) {
            $data['image_url'] = $nueva;
        }

        unset($data['imagen']);

        $supplier->update($data);

        return redirect()->route('suppliers.index')
            ->with('success', 'Proveedor «' . $supplier->name . '» actualizado.');
    }

    public function destroy(Supplier $supplier)
    {
        $nombre = $supplier->name;
        $supplier->delete();

        return redirect()->route('suppliers.index')
            ->with('success', 'Proveedor «' . $nombre . '» eliminado.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'name'         => ['required', 'string', 'max:150'],
            'tax_id'       => ['nullable', 'string', 'max:30', Rule::unique('suppliers', 'tax_id')->ignore($supplier?->id)],
            'phone'        => ['nullable', 'string', 'max:20', 'regex:/^[0-9+()\s-]+$/'],
            'email'        => ['nullable', 'email', 'max:150'],
            'address'      => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:100'],
        ] + $this->reglasImagen(), [
            'name.required' => 'El proveedor necesita un nombre o razón social.',
            'tax_id.unique' => 'Ese NIT ya está registrado en otro proveedor.',
            'email.email'   => 'Escribe un correo electrónico válido.',
            'phone.regex'   => 'El teléfono solo admite números, espacios y los signos + ( ) -',
        ] + $this->mensajesImagen());
    }
}
