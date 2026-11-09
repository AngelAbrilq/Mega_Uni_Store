<?php

namespace App\Http\Controllers\Nomina;

use App\Http\Controllers\Controller;
use App\Models\PayrollEmployee;
use App\Support\Contexto;

/**
 * NR-NOM-008 E3 · Página pública a la que lleva el QR del desprendible.
 * Muestra lo mínimo para validar (negocio, período, nombre parcial, neto):
 * nada que no esté ya impreso en el documento que se tiene en la mano.
 */
class VerificacionNominaController extends Controller
{
    public function __invoke(string $codigo)
    {
        abort_unless(preg_match('/^[A-Z0-9]{16}$/', $codigo), 404);

        $fila = Contexto::sinFiltro(fn () => PayrollEmployee::with(['payroll' => fn ($q) => $q->withoutGlobalScopes()->with('tienda.empresa'),
            'employee' => fn ($q) => $q->withoutGlobalScopes()->withTrashed()])->where('verification_code', $codigo)->first());

        $valido = $fila && $fila->payroll && ! $fila->payroll->esBorrador();

        return response()->view('nomina.verificar', compact('fila', 'valido', 'codigo'), $valido ? 200 : 404);
    }
}
