<?php

namespace App\Policies;

use App\Models\SaleReturn;
use App\Models\User;
use App\Policies\Concerns\OperaEnSuLocal;
use Illuminate\Auth\Access\Response;

/**
 * Revisar (aprobar o rechazar) una devolución pendiente. RN-27 + RN-30.
 *
 * El permiso `ventas.aprobar_devolucion` lo exige la ruta; aquí se decide
 * sobre ESTE documento: tiene que estar pendiente y revisarse desde su local,
 * que es donde la mercancía vuelve al inventario.
 */
class SaleReturnPolicy
{
    use OperaEnSuLocal;

    public function revisar(User $user, SaleReturn $devolucion): Response
    {
        if ($devolucion->status !== SaleReturn::PENDIENTE) {
            return Response::deny('Esta devolución ya fue ' . $devolucion->status . '.');
        }

        return $this->enSuLocal($devolucion);
    }
}
