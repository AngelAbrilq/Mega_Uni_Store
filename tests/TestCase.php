<?php

namespace Tests;

use App\Models\Setting;
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

        // Mismo problema con la configuración: `Setting::$memoria` es estática.
        // Una prueba que guarda `inventario.costo_metodo = ultimo` se lo heredaba
        // a la siguiente empresa con el mismo id (RefreshDatabase reusa ids).
        Setting::olvidar();
    }

    /**
     * Campos anti-spam válidos para los formularios públicos protegidos
     * con el middleware `antispam` (/probar, /register): honeypot vacío y
     * una marca de tiempo cifrada de hace un minuto.
     *
     * @return array<string, string>
     */
    protected function antispam(int $haceSegundos = 60): array
    {
        $cfg = config('sitio.antispam');

        return [
            $cfg['campo_trampa'] => '',
            $cfg['campo_tiempo'] => \Illuminate\Support\Facades\Crypt::encryptString(
                (string) (now()->timestamp - $haceSegundos)
            ),
        ];
    }
}
