<?php

namespace App\Policies;

use App\Models\Cita;
use App\Models\User;
use App\Policies\Concerns\OperaEnSuLocal;
use Illuminate\Auth\Access\Response;

/** Mover, cambiar de estado o cobrar una cita. El permiso lo exige la ruta. */
class CitaPolicy
{
    use OperaEnSuLocal;

    public function operar(User $user, Cita $cita): Response
    {
        return $this->enSuLocal($cita);
    }
}
