<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tienda\LoginClienteRequest;
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Ingreso y salida del cliente en la tienda de un negocio. */
class AccesoClienteController extends Controller
{
    public function create(Empresa $empresa): View
    {
        return view('tienda.ingresar', compact('empresa'));
    }

    public function store(LoginClienteRequest $request, Empresa $empresa): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        Auth::guard('cliente')->user()
            ->forceFill(['last_login_at' => now()])
            ->saveQuietly();

        return redirect()->intended(route('tienda.cuenta', $empresa));
    }

    /**
     * Cierra SOLO la sesión del cliente.
     *
     * No se llama a `session()->invalidate()`: la sesión del navegador es
     * una sola, y si el dueño tiene abierto el panel en otra pestaña lo
     * sacaría también. Se regenera el token CSRF y listo.
     */
    public function destroy(Request $request, Empresa $empresa): RedirectResponse
    {
        Auth::guard('cliente')->logout();

        $request->session()->regenerateToken();

        return redirect()->route('tienda.inicio', $empresa);
    }
}
