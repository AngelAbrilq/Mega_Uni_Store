<?php

namespace App\Policies;

use App\Models\SalePayment;
use App\Models\User;
use App\Policies\Concerns\OperaEnSuLocal;
use Illuminate\Auth\Access\Response;

/**
 * Verificar (aprobar o rechazar) un pago pendiente (TBL-PAG-001, RN-32).
 */
class SalePaymentPolicy
{
    use OperaEnSuLocal;

    public function verificar(User $user, SalePayment $pago): Response
    {
        if (! $user->can('ventas.verificar_pagos')) {
            return Response::deny('No tienes permiso para verificar pagos.');
        }

        // RN-27: se opera desde el local de la venta.
        return $this->enSuLocal($pago->sale);
    }
}
