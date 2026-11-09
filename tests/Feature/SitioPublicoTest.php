<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Checklist de lanzamiento del sitio público.
 *
 * Cada prueba corresponde a un punto de la lista (legal, SEO, seguridad,
 * conversión). Si alguien rompe uno —quita el aviso de cookies, vuelve a
 * meter un recurso de sicefa.com.co, agrega una segunda CTA— falla aquí
 * antes de llegar a producción.
 */
class SitioPublicoTest extends TestCase
{
    use RefreshDatabase;

    /* ═══════════════ Legal (1–3) ═══════════════ */

    public function test_paginas_legales_responden_y_estan_enlazadas_en_el_pie(): void
    {
        // Arrange
        $paginas = [
            '/aviso-legal' => 'Aviso legal',
            '/privacidad'  => 'Ley Estatutaria 1581 de 2012',
            '/cookies'     => 'Política de cookies',
        ];

        // Act + Assert
        foreach ($paginas as $url => $texto) {
            $this->get($url)->assertOk()->assertSee($texto);
        }

        $this->get('/')
            ->assertSee(route('sitio.aviso'), false)
            ->assertSee(route('sitio.privacidad'), false)
            ->assertSee(route('sitio.cookies'), false);
    }

    public function test_banner_de_cookies_presente_y_ga4_no_carga_sin_consentimiento(): void
    {
        config(['sitio.ga4_id' => 'G-TEST123']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="ck"', $html);
        // El ID viaja al script de consentimiento, pero no hay <script src> de gtag en el HTML.
        $this->assertStringNotContainsString('<script async src="https://www.googletagmanager.com', $html);
        $this->assertStringContainsString('"G-TEST123"', $html);
    }

    /* ═══════════════ HTTPS (4) ═══════════════ */

    public function test_con_https_forzado_redirige_301_y_envia_hsts(): void
    {
        config(['sitio.forzar_https' => true]);

        $this->get('http://localhost/aviso-legal')
            ->assertStatus(301)
            ->assertRedirect('https://localhost/aviso-legal');

        $this->get('https://localhost/aviso-legal')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_sin_https_forzado_no_redirige(): void
    {
        config(['sitio.forzar_https' => false]);

        $this->get('http://localhost/')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    /* ═══════════════ SEO (5–7, 9) ═══════════════ */

    public function test_landing_tiene_meta_canonico_open_graph_y_json_ld(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<title>[^<]{20,70}<\/title>/', $html);
        $this->assertStringContainsString('<meta name="description"', $html);
        $this->assertStringContainsString('<link rel="canonical"', $html);
        $this->assertStringContainsString('property="og:image"', $html);
        $this->assertStringContainsString('"@type":"SoftwareApplication"', $html);
        $this->assertStringContainsString('"@type":"WebSite"', $html);
        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
        $this->assertStringContainsString('rel="manifest"', $html);
    }

    public function test_json_ld_es_json_valido(): void
    {
        $html = $this->get('/')->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $bloques);

        $this->assertNotEmpty($bloques[1]);
        foreach ($bloques[1] as $json) {
            $datos = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            // Blade compila '@context' como directiva si se escribe fuera de @php.
            $this->assertSame('https://schema.org', $datos['@context'] ?? null);
        }
    }

    public function test_sitemap_lista_solo_urls_publicas(): void
    {
        $respuesta = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringStartsWith('application/xml', $respuesta->headers->get('Content-Type'));
        $respuesta->assertSee(route('demo.crear'), false)
            ->assertSee(route('sitio.privacidad'), false)
            ->assertDontSee('/dashboard', false)
            ->assertDontSee('/pos', false);
    }

    public function test_robots_bloquea_todo_fuera_de_produccion(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee("Disallow: /\n", false)
            ->assertSee('Sitemap: '.route('sitio.sitemap'), false);
    }

    public function test_robots_en_produccion_bloquea_el_panel_y_deja_la_landing(): void
    {
        $this->app['env'] = 'production';

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /dashboard', false)
            ->assertSee('Disallow: /sistema', false)
            ->assertSee('Allow: /', false);
    }

    public function test_manifest_es_json_con_iconos(): void
    {
        $this->get('/site.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('icons.0.sizes', '192x192');
    }

    /* ═══════════════ Imágenes y rendimiento (10–12) ═══════════════ */

    public function test_landing_no_depende_de_dominios_ajenos_y_toda_imagen_tiene_alt(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringNotContainsString('sicefa.com.co', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);

        preg_match_all('/<img\b[^>]*>/i', $html, $imgs);
        foreach ($imgs[0] as $img) {
            $this->assertMatchesRegularExpression('/\salt="/', $img, "Imagen sin alt: {$img}");
        }
    }

    /* ═══════════════ 404 y enlaces (15–16) ═══════════════ */

    public function test_404_personalizada_con_salida_al_inicio(): void
    {
        $this->get('/esta-ruta-no-existe')
            ->assertNotFound()
            ->assertSee('Esta página no existe')
            ->assertSee(route('inicio'), false);
    }

    public function test_landing_sin_enlaces_vacios(): void
    {
        $this->assertStringNotContainsString('href="#"', $this->get('/')->getContent());
    }

    /* ═══════════════ Anti-spam (17) ═══════════════ */

    public function test_honeypot_lleno_bloquea_el_envio(): void
    {
        $this->from('/probar')
            ->post('/probar', ['website' => 'http://spam.example'] + $this->antispam())
            ->assertSessionHasErrors('antispam');
    }

    public function test_envio_sin_marca_de_tiempo_se_bloquea(): void
    {
        $this->from('/probar')
            ->post('/probar', ['negocio' => 'X'])
            ->assertSessionHasErrors('antispam');
    }

    public function test_envio_demasiado_rapido_se_bloquea(): void
    {
        $this->from('/probar')
            ->post('/probar', $this->antispam(haceSegundos: 0))
            ->assertSessionHasErrors('antispam');
    }

    public function test_envio_humano_pasa_a_la_validacion_normal(): void
    {
        $this->from('/probar')
            ->post('/probar', $this->antispam())
            ->assertSessionDoesntHaveErrors('antispam')
            ->assertSessionHasErrors('negocio');
    }

    /* ═══════════════ WhatsApp (18) ═══════════════ */

    public function test_boton_whatsapp_solo_con_numero_configurado(): void
    {
        config(['sitio.whatsapp' => '']);
        $this->get('/')->assertDontSee('wa.me/', false);

        config(['sitio.whatsapp' => '573001234567']);
        $this->get('/')->assertSee('https://wa.me/573001234567', false);
    }

    /* ═══════════════ Una sola CTA (20) ═══════════════ */

    public function test_todos_los_botones_principales_llevan_a_la_misma_accion(): void
    {
        $html = $this->get('/')->getContent();

        preg_match_all('/<a class="btn btn--cta[^"]*" href="([^"]+)"/', $html, $ctas);

        $this->assertGreaterThanOrEqual(2, count($ctas[1]));
        $this->assertSame([route('demo.crear')], array_values(array_unique($ctas[1])));
    }
}
