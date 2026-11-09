<?php

namespace App\Events;

use App\Models\Payroll;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * NR-NOM-003 E6 · NR-INT-011 · Se emite al aprobar una nómina.
 * Contabilidad lo escucha para generar el asiento (gasto de personal contra
 * cuentas por pagar a empleados y entidades).
 */
class NominaAprobada
{
    use Dispatchable;

    public function __construct(public Payroll $nomina) {}
}
