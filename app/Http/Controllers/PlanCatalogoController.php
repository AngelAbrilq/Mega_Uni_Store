<?php

namespace App\Http\Controllers;

use App\Http\Requests\Plataforma\GuardarPlanRequest;
use App\Models\Plan;
use App\Services\PlanCatalogoService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * SA-PLN-001 · Catálogo de planes de la plataforma (/sistema/planes).
 *
 * Solo el Superadministrador. Lo que cada negocio paga vive en `empresas`
 * (plan, periodo, precio pactado); aquí se define lo que se vende.
 */
class PlanCatalogoController extends Controller implements HasMiddleware
{
    public function __construct(private PlanCatalogoService $catalogo)
    {
    }

    public static function middleware(): array
    {
        return [new Middleware('permission:sistema.superadmin')];
    }

    public function index()
    {
        $planes = Plan::query()
            ->withCount('empresas')
            ->orderByDesc('is_active')->orderBy('orden')->orderBy('id')
            ->get();

        return view('sistema.planes.index', ['planes' => $planes]);
    }

    public function create()
    {
        return view('sistema.planes.create', ['plan' => null]);
    }

    public function store(GuardarPlanRequest $request)
    {
        $plan = $this->catalogo->crear($request->datos());

        return redirect()->route('sistema.planes.index')->with('ok', "Plan {$plan->nombre} creado.");
    }

    public function edit(Plan $plan)
    {
        return view('sistema.planes.edit', [
            'plan'      => $plan,
            'suscritos' => $plan->empresas()->count(),
        ]);
    }

    public function update(GuardarPlanRequest $request, Plan $plan)
    {
        $congelados = $this->catalogo->actualizar($plan, $request->datos());

        $mensaje = "Plan {$plan->nombre} actualizado.";

        if ($congelados > 0) {
            $mensaje .= " {$congelados} " . ($congelados === 1 ? 'negocio conserva' : 'negocios conservan') . ' el precio anterior.';
        }

        return redirect()->route('sistema.planes.index')->with('ok', $mensaje);
    }

    public function estado(Request $request, Plan $plan)
    {
        $activo = $request->boolean('activo');
        $this->catalogo->cambiarEstado($plan, $activo);

        return back()->with('ok', $activo
            ? "Plan {$plan->nombre} activo: vuelve a ofrecerse."
            : "Plan {$plan->nombre} desactivado. Quien ya lo tiene sigue igual.");
    }

    public function destroy(Plan $plan)
    {
        $this->catalogo->eliminar($plan);

        return redirect()->route('sistema.planes.index')->with('ok', "Plan {$plan->nombre} eliminado.");
    }
}
