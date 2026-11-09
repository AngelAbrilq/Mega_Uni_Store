<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalogo\GuardarPaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PaymentMethodController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medios_pago.ver',      only: ['index', 'show']),
            new Middleware('permission:medios_pago.crear',    only: ['create', 'store']),
            new Middleware('permission:medios_pago.editar',   only: ['edit', 'update']),
            new Middleware('permission:medios_pago.eliminar', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $paymentMethods = PaymentMethod::query()
            ->when($q !== '', fn ($c) => $c->where('name', 'like', "%{$q}%"))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('payment_methods.index', compact('paymentMethods', 'q'));
    }

    public function create()
    {
        return view('payment_methods.create');
    }

    public function store(GuardarPaymentMethodRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $data['requires_verification'] = $request->boolean('requires_verification');

        $medio = PaymentMethod::create($data);

        return redirect()->route('payment_methods.index')
            ->with('success', 'Medio de pago «' . $medio->name . '» creado.');
    }

    public function show(PaymentMethod $paymentMethod)
    {
        return view('payment_methods.show', compact('paymentMethod'));
    }

    public function edit(PaymentMethod $paymentMethod)
    {
        return view('payment_methods.edit', compact('paymentMethod'));
    }

    public function update(GuardarPaymentMethodRequest $request, PaymentMethod $paymentMethod)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $data['requires_verification'] = $request->boolean('requires_verification');

        $paymentMethod->update($data);

        return redirect()->route('payment_methods.index')
            ->with('success', 'Medio de pago «' . $paymentMethod->name . '» actualizado.');
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        $nombre = $paymentMethod->name;
        $paymentMethod->delete();

        return redirect()->route('payment_methods.index')
            ->with('success', 'Medio de pago «' . $nombre . '» eliminado.');
    }
}
