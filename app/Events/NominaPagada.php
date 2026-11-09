<?php

namespace App\Events;

use App\Models\Payroll;
use Illuminate\Foundation\Events\Dispatchable;

/** NR-INT-011 E2 · Pago de la nómina: cancela la cuenta por pagar contra caja/bancos. */
class NominaPagada
{
    use Dispatchable;

    public function __construct(public Payroll $nomina) {}
}
