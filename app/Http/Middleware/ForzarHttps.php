<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTPS obligatorio y cabeceras de seguridad básicas.
 *
 * ── Por qué un middleware global y no la configuración de Apache ──
 *
 * Porque el proyecto corre en Laragon en desarrollo y en cualquier hosting
 * en producción: si la redirección dependiera del .htaccess, el día que se
 * despliegue detrás de Nginx o de un balanceador se pierde sin que nadie lo
 * note. Aquí viaja con el código.
 *
 * Se activa con `sitio.forzar_https` (true por defecto solo en producción),
 * así en localhost:8000 sin certificado no rompe nada.
 *
 * ── Detrás de un proxy ──
 *
 * Si el hosting termina el TLS en un balanceador, `$request->secure()` solo
 * es fiable con TrustProxies configurado (bootstrap/app.php →
 * `$middleware->trustProxies(at: '*')`). Sin eso esta redirección haría un
 * bucle infinito.
 */
class ForzarHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        $forzar = (bool) config('sitio.forzar_https');

        if ($forzar && ! $request->secure()) {
            // 301: el buscador consolida el ranking en la versión https.
            return redirect()->secure($request->getRequestUri(), 301);
        }

        /** @var Response $respuesta */
        $respuesta = $next($request);

        $cabeceras = $respuesta->headers;

        if ($forzar) {
            $cabeceras->set(
                'Strict-Transport-Security',
                'max-age='.(int) config('sitio.hsts_max_age', 31536000).'; includeSubDomains'
            );
        }

        // Baratas y sin efectos secundarios sobre el panel.
        if (! $cabeceras->has('X-Content-Type-Options')) {
            $cabeceras->set('X-Content-Type-Options', 'nosniff');
        }
        if (! $cabeceras->has('Referrer-Policy')) {
            $cabeceras->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        if (! $cabeceras->has('X-Frame-Options')) {
            $cabeceras->set('X-Frame-Options', 'SAMEORIGIN');
        }

        return $respuesta;
    }
}
