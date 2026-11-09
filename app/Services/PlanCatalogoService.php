<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SA-PLN-001 · El catálogo de planes de la plataforma (RN-39).
 *
 *   · Cambiar el precio NO le cambia el precio a quien ya está suscrito:
 *     antes de guardar, a cada negocio de ese plan que pagaba «precio de
 *     lista» se le congela el precio que tenía como `precio_pactado`. Así el
 *     MRR de hoy no se mueve por una decisión de catálogo.
 *   · Desactivar retira el plan de la venta (página de precios, alta y
 *     suscripción) sin tocar a los negocios que ya lo tienen.
 *   · Un plan con historia (negocios o movimientos de MRR) no se borra.
 *   · El plan de la demostración no se desactiva ni se borra: /probar lo usa.
 */
class PlanCatalogoService
{
    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Plan
    {
        return Plan::create($datos);
    }

    /**
     * @param array<string, mixed> $datos
     * @return int Negocios a los que se les congeló el precio.
     */
    public function actualizar(Plan $plan, array $datos): int
    {
        return DB::transaction(function () use ($plan, $datos) {
            $congelados = 0;

            foreach (['mensual' => 'precio_mensual', 'anual' => 'precio_anual'] as $periodo => $columna) {
                if (! array_key_exists($columna, $datos) || (float) $datos[$columna] === (float) $plan->{$columna}) {
                    continue;
                }

                $congelados += Empresa::query()
                    ->where('plan_id', $plan->id)
                    ->where('periodo', $periodo)
                    ->whereNull('precio_pactado')
                    ->update(['precio_pactado' => $plan->{$columna}]);
            }

            $plan->update($datos);

            return $congelados;
        });
    }

    public function cambiarEstado(Plan $plan, bool $activo): void
    {
        if (! $activo && $plan->es_demo) {
            throw ValidationException::withMessages([
                'plan' => 'El plan de la demostración no se puede desactivar: /probar lo necesita.',
            ]);
        }

        $plan->forceFill(['is_active' => $activo])->save();
    }

    public function eliminar(Plan $plan): void
    {
        if ($plan->es_demo || $plan->enUso()) {
            throw ValidationException::withMessages([
                'plan' => $plan->es_demo
                    ? 'El plan de la demostración no se puede borrar.'
                    : 'Este plan tiene negocios o historia de ingresos. Desactívalo en lugar de borrarlo.',
            ]);
        }

        $plan->delete();
    }
}
