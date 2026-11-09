<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Dispara la animación de bienvenida en el layout del panel.
        // Solo vive una petición: al recargar el dashboard ya no aparece.
        $request->session()->flash('mus_welcome', $request->user()->name);
        $request->session()->flash('mus_welcome_new', false);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $nombre = $request->user()?->name;

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // Aviso que se muestra al aterrizar en el inicio.
        $request->session()->flash(
            'mus_bye',
            $nombre
                ? 'Hasta pronto, ' . \Illuminate\Support\Str::of($nombre)->trim()->explode(' ')->first() . '.'
                : 'Tu sesión se cerró correctamente.'
        );

        return redirect('/');
    }
}
