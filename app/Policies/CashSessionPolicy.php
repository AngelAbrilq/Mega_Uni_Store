<?php

namespace App\Policies;

use App\Models\CashSession;
use App\Models\User;
use App\Policies\Concerns\OperaEnSuLocal;
use Illuminate\Auth\Access\Response;

/**
 * Cerrar (arquear) un turno de caja.
 *
 * Cada cajero cierra el suyo. Cerrar el de OTRA persona —el cajero se fue
 * sin arquear— exige `caja.cerrar_ajena` (Administrador y Supervisor).
 */
class CashSessionPolicy
{
    use OperaEnSuLocal;

    public function cerrar(User $user, CashSession $cash): Response
    {
        $local = $this->enSuLocal($cash);

        if ($local->denied()) {
            return $local;
        }

        if ((int) $cash->user_id === (int) $user->id) {
            return $user->can('caja.cerrar')
                ? Response::allow()
                : Response::deny('No tienes permiso para cerrar turnos de caja.');
        }

        return $user->can('caja.cerrar_ajena')
            ? Response::allow()
            : Response::deny('Este turno lo abrió otra persona: solo un supervisor o el administrador puede cerrarlo.');
    }

    /**
     * Registrar un ingreso o retiro de efectivo (AT-CAJ-001).
     *
     * Mismas reglas que cerrar: en su local, en su propio turno con
     * `caja.movimientos`, y en el turno de otra persona solo quien puede
     * arquearlo (`caja.cerrar_ajena`).
     */
    public function moverEfectivo(User $user, CashSession $cash): Response
    {
        $local = $this->enSuLocal($cash);

        if ($local->denied()) {
            return $local;
        }

        if (! $user->can('caja.movimientos')) {
            return Response::deny('No tienes permiso para registrar ingresos o retiros de caja.');
        }

        if ((int) $cash->user_id === (int) $user->id || $user->can('caja.cerrar_ajena')) {
            return Response::allow();
        }

        return Response::deny('Este turno lo abrió otra persona: solo un supervisor o el administrador puede moverle efectivo.');
    }
}
