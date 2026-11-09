<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;
use App\Policies\Concerns\OperaEnSuLocal;
use Illuminate\Auth\Access\Response;

/** Acciones sobre una venta ya registrada. El permiso lo exige la ruta. */
class SalePolicy
{
    use OperaEnSuLocal;

    public function anular(User $user, Sale $sale): Response
    {
        return $this->enSuLocal($sale);
    }

    /** VD-VTA-002: se pide desde el local de la venta, como anular. */
    public function solicitarAnulacion(User $user, Sale $sale): Response
    {
        return $this->enSuLocal($sale);
    }

    public function devolver(User $user, Sale $sale): Response
    {
        return $this->enSuLocal($sale);
    }
}
