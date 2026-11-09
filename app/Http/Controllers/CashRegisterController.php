<?php

namespace App\Http\Controllers;

use App\Http\Requests\Caja\GuardarCajaRegistradoraRequest;
use App\Models\CashRegister;
use App\Models\CashSession;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Cajas registradoras físicas del local activo (AT-CAJ-001).
 *
 * Solo se administran las del local en el que se está trabajando: cada
 * local define las suyas («Caja 1 · Principal», «Caja 2 · Rápida»).
 */
class CashRegisterController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('permission:caja.gestionar_cajas')];
    }

    public function index()
    {
        $cajas = CashRegister::deTienda(null)
            ->withCount('sessions')
            ->with(['sessions' => fn ($q) => $q->abierta()->with('user:id,name,last_name')])
            ->orderByDesc('is_active')
            ->orderBy('code')
            ->orderBy('name')
            ->get();

        return view('cash_registers.index', compact('cajas'));
    }

    public function create()
    {
        return view('cash_registers.create');
    }

    public function store(GuardarCajaRegistradoraRequest $request)
    {
        $caja = CashRegister::create($request->validated() + ['is_active' => true]);

        return redirect()->route('cash_registers.index')
            ->with('success', 'Caja «' . $caja->etiqueta . '» creada.');
    }

    public function edit(CashRegister $cashRegister)
    {
        return view('cash_registers.edit', ['caja' => $cashRegister]);
    }

    public function update(GuardarCajaRegistradoraRequest $request, CashRegister $cashRegister)
    {
        $datos = $request->validated();

        // Desactivar una caja con el turno abierto dejaría ese turno sin caja usable.
        if (array_key_exists('is_active', $datos) && ! $datos['is_active'] && $cashRegister->turnoAbierto()) {
            return back()->withInput()
                ->with('error', 'Esta caja tiene un turno abierto. Ciérralo antes de desactivarla.');
        }

        $cashRegister->update($datos);

        return redirect()->route('cash_registers.index')
            ->with('success', 'Caja «' . $cashRegister->etiqueta . '» actualizada.');
    }

    /**
     * Una caja con turnos en su historial no se borra: se desactiva, para
     * que los arqueos viejos sigan diciendo en qué caja se hicieron.
     */
    public function destroy(CashRegister $cashRegister)
    {
        if ($cashRegister->turnoAbierto()) {
            return back()->with('error', 'Esta caja tiene un turno abierto. Ciérralo antes de retirarla.');
        }

        if ($cashRegister->sessions()->exists()) {
            $cashRegister->update(['is_active' => false]);

            return redirect()->route('cash_registers.index')
                ->with('success', 'La caja «' . $cashRegister->etiqueta . '» tiene historial: quedó desactivada.');
        }

        $etiqueta = $cashRegister->etiqueta;
        $cashRegister->delete();

        return redirect()->route('cash_registers.index')
            ->with('success', 'Caja «' . $etiqueta . '» eliminada.');
    }
}
