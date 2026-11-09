<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Portada y cuenta del cliente.
 *
 * La portada es un esqueleto: el catálogo entra en la Fase 2.
 */
class PortalController extends Controller
{
    public function inicio(Empresa $empresa): View
    {
        return view('tienda.inicio', [
            'empresa' => $empresa,
            'cliente' => Auth::guard('cliente')->user(),
        ]);
    }

    public function cuenta(Empresa $empresa): View
    {
        return view('tienda.cuenta', [
            'empresa' => $empresa,
            'cliente' => Auth::guard('cliente')->user(),
        ]);
    }
}
