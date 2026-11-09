<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anti-spam sin CAPTCHA para los formularios públicos.
 *
 * ── Dos trampas, cero fricción ──
 *
 * 1. Honeypot: un campo `website` que el humano no ve (CSS + aria-hidden +
 *    tabindex -1). Los bots que rellenan todo lo que encuentran lo llenan.
 * 2. Tiempo mínimo: `<x-mus.antispam />` pinta la hora de carga CIFRADA
 *    con la APP_KEY. Un envío que llega antes de N segundos —o sin la
 *    marca, o con una marca manipulada o caducada— no lo hizo una persona.
 *
 * Se combina con el `throttle` de cada ruta: el throttle frena el volumen,
 * esto frena el envío automático individual.
 *
 * ── Por qué no reCAPTCHA ──
 *
 * Porque obliga a cargar un script de Google en cada visita (velocidad y
 * consentimiento de cookies) y le pone un acertijo a quien venía a probar
 * el sistema. Si algún día el volumen de bots lo exige, se agrega encima.
 *
 * Al bot se le responde con un error de validación genérico: no se le
 * cuenta cuál trampa pisó.
 */
class ProtegeFormulario
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST')) {
            return $next($request);
        }

        $cfg = config('sitio.antispam');

        if ($this->esBot($request, $cfg)) {
            Log::warning('antispam: envío bloqueado', [
                'ruta' => $request->path(),
                'ip'   => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'antispam' => 'No pudimos procesar el envío. Espera unos segundos y vuelve a intentarlo.',
            ]);
        }

        return $next($request);
    }

    /**
     * @param  array{campo_trampa:string, campo_tiempo:string, segundos_min:int, vigencia_horas:int}  $cfg
     */
    private function esBot(Request $request, array $cfg): bool
    {
        // 1. Honeypot lleno.
        if (filled($request->input($cfg['campo_trampa']))) {
            return true;
        }

        // 2. Marca de tiempo ausente, ilegible o fuera de rango.
        $marca = (string) $request->input($cfg['campo_tiempo'], '');
        if ($marca === '') {
            return true;
        }

        try {
            $cargado = (int) Crypt::decryptString($marca);
        } catch (\Throwable) {
            return true;
        }

        $transcurrido = now()->timestamp - $cargado;

        return $transcurrido < $cfg['segundos_min']
            || $transcurrido > $cfg['vigencia_horas'] * 3600;
    }
}
