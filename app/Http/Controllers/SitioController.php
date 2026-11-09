<?php

namespace App\Http\Controllers;

use App\Services\DemoService;
use App\Support\Rubros;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Sitio público: landing, páginas legales, sitemap y robots.
 *
 * Nada de aquí toca datos de los inquilinos: son páginas iguales para
 * todos, por eso el sitemap puede cachearse sin empresa_id en la clave
 * (a diferencia de H-01 en AlertService).
 */
class SitioController extends Controller
{
    /** Páginas legales disponibles: slug de ruta => vista. */
    private const LEGALES = [
        'aviso-legal' => 'sitio.legal.aviso',
        'privacidad'  => 'sitio.legal.privacidad',
        'cookies'     => 'sitio.legal.cookies',
    ];

    /**
     * Landing. Los rubros salen del mismo catálogo que siembra la prueba
     * (Rubros) para que lo que se promete aquí sea lo que el visitante
     * encuentra adentro.
     */
    public function inicio(): View
    {
        return view('welcome', [
            'rubros'  => Rubros::paraEscoger(),
            'minutos' => DemoService::MINUTOS,
        ]);
    }

    public function aviso(): View
    {
        return view(self::LEGALES['aviso-legal']);
    }

    public function privacidad(): View
    {
        return view(self::LEGALES['privacidad']);
    }

    public function cookies(): View
    {
        return view(self::LEGALES['cookies']);
    }

    /**
     * sitemap.xml con las URL públicas indexables.
     *
     * Solo entra lo que un buscador debe mostrar: nada del panel, nada que
     * pida sesión. Se cachea 1 día; `php artisan cache:clear` lo regenera.
     */
    public function sitemap(): Response
    {
        $xml = Cache::remember('sitio.sitemap.'.md5((string) config('app.url')), 86400, function (): string {
            $vigencia = (string) config('sitio.legal.vigencia');

            $urls = [
                ['loc' => route('inicio'),           'prioridad' => '1.0', 'frecuencia' => 'weekly'],
                ['loc' => route('demo.crear'),       'prioridad' => '0.9', 'frecuencia' => 'monthly'],
                ['loc' => route('sitio.aviso'),      'prioridad' => '0.3', 'frecuencia' => 'yearly'],
                ['loc' => route('sitio.privacidad'), 'prioridad' => '0.3', 'frecuencia' => 'yearly'],
                ['loc' => route('sitio.cookies'),    'prioridad' => '0.3', 'frecuencia' => 'yearly'],
            ];

            return view('sitio.sitemap', compact('urls', 'vigencia'))->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * robots.txt dinámico: la línea Sitemap exige URL absoluta, y esa URL
     * cambia entre localhost y producción. Fuera de producción se bloquea
     * todo para que un staging no termine indexado.
     */
    public function robots(): Response
    {
        $privadas = [
            '/dashboard', '/pos', '/sales', '/returns', '/cash', '/inventory',
            '/purchases', '/smart-inventory', '/customers', '/suppliers',
            '/products', '/categories', '/units', '/taxes', '/attributes',
            '/payment_methods', '/agenda', '/recursos', '/traslados', '/tiendas',
            '/reports', '/users', '/roles', '/audit', '/plan', '/sistema',
            '/settings', '/profile', '/buscar', '/api/', '/storage/',
            '/forgot-password', '/reset-password', '/verify-email', '/confirm-password',
        ];

        $lineas = ['User-agent: *'];

        if (app()->isProduction()) {
            foreach ($privadas as $ruta) {
                $lineas[] = 'Disallow: '.$ruta;
            }
            $lineas[] = 'Allow: /';
        } else {
            $lineas[] = 'Disallow: /';
        }

        $lineas[] = '';
        $lineas[] = 'Sitemap: '.route('sitio.sitemap');

        return response(implode("\n", $lineas)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Web App Manifest (favicon Android / "Agregar a inicio"). */
    public function manifest(): Response
    {
        $datos = [
            'name'             => config('sitio.marca'),
            'short_name'       => 'MUS',
            'description'      => config('sitio.eslogan'),
            'start_url'        => '/',
            'display'          => 'standalone',
            'background_color' => '#0A1119',
            'theme_color'      => '#0E1723',
            'lang'             => 'es-CO',
            'icons'            => [
                ['src' => asset('img/sitio/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => asset('img/sitio/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ];

        return response(json_encode($datos, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200, [
            'Content-Type' => 'application/manifest+json',
        ]);
    }
}
