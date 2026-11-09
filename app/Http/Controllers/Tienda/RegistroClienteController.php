<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tienda\RegistroClienteRequest;
use App\Models\Empresa;
use App\Services\Tienda\RegistroClienteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegistroClienteController extends Controller
{
    public function __construct(private readonly RegistroClienteService $registro)
    {
    }

    public function create(Empresa $empresa): View
    {
        return view('tienda.registro', compact('empresa'));
    }

    public function store(RegistroClienteRequest $request, Empresa $empresa): RedirectResponse
    {
        $cliente = $this->registro->registrar($request->safe()->only([
            'first_name', 'last_name', 'email', 'phone', 'password',
        ]) + ['last_name' => null, 'phone' => null]);

        Auth::guard('cliente')->login($cliente);

        $request->session()->regenerate();

        return redirect()->route('tienda.cuenta', $empresa)
            ->with('ok', '¡Bienvenido! Tu cuenta quedó creada.');
    }
}
