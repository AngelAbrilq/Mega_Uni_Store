<?php

namespace App\Http\Middleware;

use App\Services\SesionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * TBL-SES-001 · Valida en cada petición que la sesión siga activa y vigente
 * (RN-41). Una sesión cerrada a distancia o vencida saca a la persona en la
 * siguiente petición, no cuando caduque la cookie.
 *
 * El portal de clientes (tienda.*) usa otro guard y no pasa por aquí.
 */
class ControlDeSesion
{
    public function __construct(private SesionService $sesiones)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('tienda.*') || ! $request->hasSession()) {
            return $next($request);
        }

        $usuario = Auth::guard('web')->user();

        if (! $usuario || $this->sesiones->controlar($request, $usuario)) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $mensaje = 'Tu sesión terminó (venció o la cerraron desde otro dispositivo). Vuelve a ingresar.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $mensaje], 401);
        }

        return redirect()->route('login')->withErrors(['email' => $mensaje]);
    }
}
