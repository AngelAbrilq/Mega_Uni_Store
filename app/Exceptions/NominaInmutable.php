<?php

namespace App\Exceptions;

use RuntimeException;

/** NR-NOM-010 · Escenario 4. */
class NominaInmutable extends RuntimeException
{
    public const MENSAJE = 'Las nóminas aprobadas son inmutables. Para corregir, debes crear una nómina de ajuste.';

    public function __construct()
    {
        parent::__construct(self::MENSAJE);
    }
}
