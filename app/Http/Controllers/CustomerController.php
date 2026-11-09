<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalogo\GuardarCustomerRequest;
use App\Http\Controllers\Concerns\GuardaImagen;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

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

    public function store(GuardarCustomerRequest $request)
    {
        $data = $request->validated();
        $data['image_url'] = $this->guardarImagen($request, 'clientes');

        unset($data['imagen']);

        $cliente = Customer::create($data);

        return redirect()->route('customers.index')
            ->with('success', 'Cliente «' . $cliente->full_name . '» registrado.');
    }

    public function show(Request $request, Customer $customer)
    {
        $customer->load(['tiendas' => fn ($q) => $q->orderByDesc('points_balance')]);

        $locales     = \App\Models\Tienda::where('empresa_id', \App\Support\Contexto::empresaId())->pluck('nombre', 'id');
        $misLocales  = $request->user()->tiendasEn((int) \App\Support\Contexto::empresaId())->pluck('nombre', 'id');
        $asociados   = $customer->tiendas->where('is_active', true)->pluck('tienda_id')->all();
        $movimientos = \App\Models\LoyaltyTransaction::withoutGlobalScopes()
            ->with('sale:id,number')
            ->where('customer_id', $customer->id)
            ->latest('id')->limit(10)->get();

        return view('customers.show', [
            'customer'    => $customer,
            'locales'     => $locales,
            'porAsociar'  => $misLocales->except($asociados),
            'misLocales'  => $misLocales->keys()->all(),
            'movimientos' => $movimientos,
            'puntosActivos' => \App\Services\FidelizacionService::activa(),
        ]);
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', [
            'customer'   => $customer,
            'documentos' => self::DOCUMENTOS,
        ]);
    }

    public function update(GuardarCustomerRequest $request, Customer $customer)
    {
        $data  = $request->validated();
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
}
