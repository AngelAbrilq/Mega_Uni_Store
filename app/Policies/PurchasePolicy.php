<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;
use App\Policies\Concerns\OperaEnSuLocal;
use Illuminate\Auth\Access\Response;

/** Acciones sobre una compra. El permiso lo exige la ruta. */
class PurchasePolicy
{
    use OperaEnSuLocal;

    public function update(User $user, Purchase $purchase): Response
    {
        return $this->enSuLocal($purchase);
    }

    /** H-F12 · La mercancía entra al local de la compra, no al de quien hace clic. */
    public function recibir(User $user, Purchase $purchase): Response
    {
        return $this->enSuLocal($purchase);
    }

    public function anular(User $user, Purchase $purchase): Response
    {
        return $this->enSuLocal($purchase);
    }
}
