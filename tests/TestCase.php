<?php

namespace Tests;

use App\Support\Contexto;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * El contexto se limpia antes de cada prueba.
     *
     * `Contexto` guarda el negocio activo en propiedades estáticas, y las
     * estáticas sobreviven de una prueba a la siguiente dentro del mismo
     * proceso de PHPUnit. Sin esta línea, una prueba que termina parada en
     * la empresa 2 le pasa esa empresa a la siguiente —que crea la suya
     * desde cero y espera empezar en blanco—, y el fallo aparece en una
     * prueba que no tiene nada que ver con la que lo causó.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Contexto::olvidar();
    }
}
