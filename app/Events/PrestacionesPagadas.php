<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

/** NR-NOM-007 E5 · Pago de prestaciones (Débito gasto prestaciones, Crédito banco). */
class PrestacionesPagadas
{
    use Dispatchable;

    /** @param Collection<int, \App\Models\SocialBenefit> $prestaciones */
    public function __construct(public Collection $prestaciones) {}
}
