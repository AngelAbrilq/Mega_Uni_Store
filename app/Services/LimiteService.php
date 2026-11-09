<?php

namespace App\Services;

use App\Exceptions\LimiteDelPlan;
use App\Models\Empresa;
use App\Support\Contexto;

/**
 * ¿Le cabe uno más?
 *
 * ── Dónde se pregunta ──
 *
 * Justo antes de crear, no al pintar el botón. Esconder el botón está bien
 * para no frustrar a nadie, pero no es una comprobación: el formulario se
 * puede mandar igual desde afuera. La comprobación va donde se escribe.
 *
 * ── Qué NO hace ──
 *
 * No borra ni bloquea nada de lo que ya existe. Si un cliente se baja de
 * plan y le sobran cincuenta productos, esos cincuenta se quedan y se
 * siguen vendiendo; lo que no puede es crear el cincuenta y uno. Bajar de
 * plan no puede hacerle perder datos a nadie —eso sería castigar al
 * cliente por gastar menos, y además borrar cosas suyas sin permiso—.
 */
class LimiteService
{
    /**
     * Comprueba y deja pasar, o corta la operación.
     *
     * @throws LimiteDelPlan
     */
    public function exigir(string $recurso, int $cuantos = 1, ?Empresa $empresa = null): void
    {
        $empresa ??= Contexto::empresa();

        // Sin empresa no hay plan que hacer valer. Pasa: el aislamiento es
        // problema del middleware, no de la facturación.
        if (! $empresa) {
            return;
        }

        if ($empresa->puedeAgregar($recurso, $cuantos)) {
            return;
        }

        throw new LimiteDelPlan(
            $recurso,
            (int) $empresa->limite($recurso),
            (string) ($empresa->plan?->nombre ?? 'actual'),
        );
    }

    /** La misma pregunta, sin cortar nada. Para esconder botones. */
    public function permite(string $recurso, int $cuantos = 1, ?Empresa $empresa = null): bool
    {
        $empresa ??= Contexto::empresa();

        return $empresa === null || $empresa->puedeAgregar($recurso, $cuantos);
    }

    /**
     * Cómo va de lleno cada recurso.
     *
     * Devuelve, por recurso: usados, tope y porcentaje. Es lo que alimenta
     * la barra de «vas por 45 de 50 productos», que es la única forma de
     * que un límite no llegue de sorpresa.
     *
     * @return array<string, array{usado:int, limite:int|null, porcentaje:int|null, apretado:bool}>
     */
    public function panorama(?Empresa $empresa = null): array
    {
        $empresa ??= Contexto::empresa();

        if (! $empresa) {
            return [];
        }

        $salida = [];

        foreach (\App\Models\Plan::RECURSOS as $recurso) {
            $limite = $empresa->limite($recurso);
            $usado  = $empresa->usado($recurso);

            $porcentaje = $limite && $limite > 0
                ? (int) min(100, round($usado / $limite * 100))
                : null;

            $salida[$recurso] = [
                'usado'      => $usado,
                'limite'     => $limite,
                'porcentaje' => $porcentaje,
                // Se avisa desde el 80%: a esa altura todavía hay tiempo de
                // hacer algo. Avisar al 100% ya no es un aviso, es la queja.
                'apretado'   => $porcentaje !== null && $porcentaje >= 80,
            ];
        }

        return $salida;
    }
}
