<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\LimiteService;
use App\Services\MrrService;
use App\Support\Contexto;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * «¿Qué plan tengo y cuánto llevo gastado de él?»
 *
 * ── Por qué existe esta pantalla ──
 *
 * Un límite que llega de sorpresa se siente como una falla del sistema.
 * El cajero intenta guardar el producto número 501, sale un mensaje que
 * habla de planes, y la reacción no es «hay que subir de plan» sino «esto
 * se dañó».
 *
 * La barra que muestra 480 de 500 evita esa conversación entera. No hace
 * falta que nadie la mire todos los días: basta con que exista el día que
 * el mensaje aparezca, para que se entienda de dónde salió.
 *
 * Es de solo lectura a propósito. Cambiar de plan es una conversación
 * comercial —hay descuentos pactados, hay periodos, hay sedes— y ponerle
 * un botón «subir de plan» sin cobro por detrás sería prometer algo que
 * el sistema todavía no puede cumplir.
 */
class PlanController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        // El mismo permiso que la configuración: es información del dueño,
        // no del cajero.
        return [new Middleware('permission:configuracion.editar')];
    }

    public function __invoke(LimiteService $limites, MrrService $mrr)
    {
        $empresa = Contexto::empresa();

        return view('plan.index', [
            'empresa'   => $empresa,
            'plan'      => $empresa?->plan,
            'panorama'  => $limites->panorama($empresa),
            'catalogo'  => Plan::vendibles()->get(),
            'aporte'    => $empresa?->mrr() ?? 0,
            // Los últimos movimientos de ESTA empresa. El consolidado de
            // todos los clientes es del panel de superadministrador, que es
            // otra pantalla y otro permiso.
            'historial' => $empresa?->movimientosMrr()->with('planDespues')->limit(10)->get() ?? collect(),
        ]);
    }
}
