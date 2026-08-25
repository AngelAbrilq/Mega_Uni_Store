<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\MrrMovimiento;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * Todo cambio de suscripción pasa por aquí.
 *
 * ── Por qué un servicio y no `$empresa->update(['plan_id' => …])` ──
 *
 * Porque cambiar el plan no es cambiar una columna: es un hecho comercial
 * que hay que registrar. Si se pudiera cambiar por fuera, la bitácora del
 * ingreso tendría huecos —y una bitácora con huecos es peor que ninguna,
 * porque los números que salen de ella parecen ciertos y no lo son—.
 *
 * Todos los métodos de aquí calculan el MRR antes, aplican el cambio,
 * calculan el después y escriben la línea. En una transacción: o queda
 * todo, o no queda nada. Nunca un plan cambiado sin su movimiento.
 */
class MrrService
{
    /**
     * Suscribe o cambia de plan.
     *
     * El tipo de movimiento no se pide: se deduce de los números, que es
     * lo único que no se puede equivocar. Si el MRR sube es expansión, si
     * baja es contracción, y si antes era cero es un cliente nuevo.
     */
    public function cambiarPlan(
        Empresa $empresa,
        ?Plan $plan,
        string $periodo = 'mensual',
        ?float $precioPactado = null,
        ?string $motivo = null,
        ?int $tiendasContratadas = null,
    ): MrrMovimiento {
        return DB::transaction(function () use ($empresa, $plan, $periodo, $precioPactado, $motivo, $tiendasContratadas) {
            $antes     = $empresa->mrr();
            $planAntes = $empresa->plan_id;

            $empresa->plan_id        = $plan?->id;
            $empresa->periodo        = $periodo;
            $empresa->precio_pactado = $precioPactado;

            if ($tiendasContratadas !== null) {
                $empresa->tiendas_contratadas = $tiendasContratadas;
            }

            // La fecha de suscripción se pone una sola vez: es la del
            // primer plan, no la del último cambio. Sirve para saber cuánto
            // lleva el cliente, que es la mitad del cálculo del LTV.
            if ($empresa->suscrita_desde === null && $plan !== null) {
                $empresa->suscrita_desde = now()->toDateString();
            }

            $empresa->save();
            $empresa->unsetRelation('plan');

            $despues = $empresa->fresh()->mrr();

            return $this->anotar($empresa, $this->deducirTipo($antes, $despues), $antes, $despues, $planAntes, $plan?->id, $motivo);
        });
    }

    /**
     * El cliente se va.
     *
     * Se le quita el plan y se suspende. No se borra la empresa: sus datos
     * son suyos, y si vuelve en marzo —que es lo que hacen los negocios de
     * temporada— tiene que encontrar todo donde lo dejó.
     */
    public function darDeBaja(Empresa $empresa, ?string $motivo = null): MrrMovimiento
    {
        return DB::transaction(function () use ($empresa, $motivo) {
            $antes     = $empresa->mrr();
            $planAntes = $empresa->plan_id;

            $empresa->estado         = 'suspendida';
            $empresa->plan_id        = null;
            $empresa->precio_pactado = null;
            $empresa->save();
            $empresa->unsetRelation('plan');

            return $this->anotar($empresa, 'baja', $antes, 0.0, $planAntes, null, $motivo);
        });
    }

    /**
     * Vuelve un cliente que se había ido.
     *
     * Se anota como reactivación y no como nuevo aunque el efecto en el
     * MRR sea el mismo. La diferencia importa para el CAC: traer de vuelta
     * a alguien que ya te conoce cuesta muy distinto que conseguir a uno
     * que nunca te ha visto, y mezclarlos en el mismo promedio hace que
     * ninguno de los dos números sirva.
     */
    public function reactivar(
        Empresa $empresa,
        Plan $plan,
        string $periodo = 'mensual',
        ?float $precioPactado = null,
        ?string $motivo = null,
    ): MrrMovimiento {
        return DB::transaction(function () use ($empresa, $plan, $periodo, $precioPactado, $motivo) {
            $antes = $empresa->mrr();

            $empresa->estado         = 'activa';
            $empresa->expira_en      = null;
            $empresa->plan_id        = $plan->id;
            $empresa->periodo        = $periodo;
            $empresa->precio_pactado = $precioPactado;
            $empresa->save();
            $empresa->unsetRelation('plan');

            $despues = $empresa->fresh()->mrr();

            return $this->anotar($empresa, 'reactivacion', $antes, $despues, null, $plan->id, $motivo);
        });
    }

    /** Suma o quita sedes contratadas. Casi siempre es expansión. */
    public function cambiarSedes(Empresa $empresa, int $contratadas, ?string $motivo = null): MrrMovimiento
    {
        return DB::transaction(function () use ($empresa, $contratadas, $motivo) {
            $antes = $empresa->mrr();

            $empresa->tiendas_contratadas = max(1, $contratadas);
            $empresa->save();

            $despues = $empresa->fresh()->mrr();

            return $this->anotar(
                $empresa,
                $this->deducirTipo($antes, $despues),
                $antes,
                $despues,
                $empresa->plan_id,
                $empresa->plan_id,
                $motivo ?? 'Cambio a ' . $empresa->tiendas_contratadas . ' sede(s)'
            );
        });
    }

    /* ═══════════════ Lectura ═══════════════ */

    /**
     * El MRR de hoy: la suma de lo que aporta cada cliente activo.
     *
     * Se recalcula y no se guarda en ningún lado. Un total guardado se
     * desincroniza el día que alguien toque una empresa por fuera del
     * servicio, y un número de ingreso desincronizado no se nota hasta que
     * se usa para decidir algo.
     */
    public function mrrActual(): float
    {
        return round(
            Empresa::query()
                ->with('plan')
                ->where('estado', 'activa')
                ->where('es_demo', false)
                ->get()
                ->sum(fn (Empresa $e) => $e->mrr()),
            2
        );
    }

    public function arrActual(): float
    {
        return round($this->mrrActual() * 12, 2);
    }

    /**
     * Cómo se movió el ingreso en un mes.
     *
     * @return array<string, float|int>
     */
    public function resumenDelMes(?string $mes = null): array
    {
        $movimientos = MrrMovimiento::delMes($mes)->get();

        $porTipo = fn (string $tipo) => round(
            (float) $movimientos->where('tipo', $tipo)->sum('delta'),
            2
        );

        $nuevo        = $porTipo('nuevo');
        $expansion    = $porTipo('expansion');
        $reactivacion = $porTipo('reactivacion');
        $contraccion  = $porTipo('contraccion');
        $baja         = $porTipo('baja');

        return [
            'nuevo'        => $nuevo,
            'expansion'    => $expansion,
            'reactivacion' => $reactivacion,
            'contraccion'  => $contraccion,   // negativo
            'baja'         => $baja,          // negativo
            'neto'         => round($nuevo + $expansion + $reactivacion + $contraccion + $baja, 2),
            'clientes'     => $movimientos->pluck('empresa_id')->unique()->count(),
        ];
    }

    /* ═══════════════ Interno ═══════════════ */

    /**
     * Qué clase de movimiento fue, según los números.
     *
     * De cero a algo es un cliente nuevo; de algo a cero es una baja. Lo
     * demás es subir o bajar. La reactivación no se deduce —se ve igual
     * que un cliente nuevo— y por eso tiene su propio método.
     */
    private function deducirTipo(float $antes, float $despues): string
    {
        if ($antes <= 0 && $despues > 0) {
            return 'nuevo';
        }

        if ($antes > 0 && $despues <= 0) {
            return 'baja';
        }

        // De cero a cero: un plan sin costo —la demo, una cortesía—. Sigue
        // siendo un alta aunque no mueva el ingreso, y anotarlo como
        // «expansión de $0» sería ensuciar el informe con una línea que no
        // cuenta nada.
        if ($antes <= 0 && $despues <= 0) {
            return 'nuevo';
        }

        return $despues >= $antes ? 'expansion' : 'contraccion';
    }

    private function anotar(
        Empresa $empresa,
        string $tipo,
        float $antes,
        float $despues,
        ?int $planAntes,
        ?int $planDespues,
        ?string $motivo,
    ): MrrMovimiento {
        return MrrMovimiento::create([
            'empresa_id'      => $empresa->id,
            'tipo'            => $tipo,
            'plan_antes_id'   => $planAntes,
            'plan_despues_id' => $planDespues,
            'mrr_antes'       => round($antes, 2),
            'mrr_despues'     => round($despues, 2),
            'delta'           => round($despues - $antes, 2),
            'motivo'          => $motivo,
            'ocurrio_en'      => now()->toDateString(),
        ]);
    }
}
