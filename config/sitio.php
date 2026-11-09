<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Sitio público (landing, páginas legales, SEO y conversión)
|--------------------------------------------------------------------------
| Todo lo que identifica al responsable del sitio vive en .env y no en las
| vistas: así los datos legales, el JSON-LD, la ficha de Google y el botón
| de WhatsApp dicen SIEMPRE lo mismo (Google penaliza los datos NAP
| —nombre, dirección, teléfono— que no coinciden entre fuentes).
|
| Regla de las vistas: un valor vacío NO se renderiza. Un WhatsApp sin
| número no muestra botón; un GA4 sin ID no carga ningún script.
*/

return [

    /* ── Identidad comercial ── */
    'marca'       => env('SITIO_MARCA', env('APP_NAME', 'MEGA UNI STORE')),
    'eslogan'     => env('SITIO_ESLOGAN', 'ERP y punto de venta en la nube para negocios en Colombia'),
    'descripcion' => env(
        'SITIO_DESCRIPCION',
        'Vende, controla inventario, cuadra caja y mide tu negocio desde un solo sistema. Punto de venta, compras, kardex y reportes. Pruébalo gratis 2 h 30 sin tarjeta.'
    ),

    /* ── Responsable del tratamiento (Ley 1581 de 2012 / Decreto 1377 de 2013) ── */
    'legal' => [
        'razon_social' => env('SITIO_RAZON_SOCIAL', ''),
        'nit'          => env('SITIO_NIT', ''),
        'direccion'    => env('SITIO_DIRECCION', ''),
        'ciudad'       => env('SITIO_CIUDAD', 'Hobo'),
        'departamento' => env('SITIO_DEPARTAMENTO', 'Huila'),
        'pais'         => env('SITIO_PAIS', 'CO'),
        'codigo_postal'=> env('SITIO_CODIGO_POSTAL', ''),
        'email'        => env('SITIO_EMAIL', ''),
        'telefono'     => env('SITIO_TELEFONO', ''),
        // Fecha de la última versión de las políticas (se muestra al pie de cada una).
        'vigencia'     => env('SITIO_LEGAL_VIGENCIA', '2026-10-05'),
    ],

    /* ── Conversión ── */
    // Solo dígitos con indicativo: 573001234567. Vacío = sin botón.
    'whatsapp'         => preg_replace('/\D+/', '', (string) env('SITIO_WHATSAPP', '')),
    'whatsapp_mensaje' => env('SITIO_WHATSAPP_MENSAJE', 'Hola, quiero saber más sobre MEGA UNI STORE.'),

    /* ── Analítica (solo carga tras el consentimiento de cookies) ── */
    'ga4_id' => env('SITIO_GA4_ID', ''),   // G-XXXXXXXXXX

    /* ── Redes (vacías = no se muestran; nunca href="#") ── */
    'redes' => array_filter([
        'instagram' => env('SITIO_INSTAGRAM', ''),
        'facebook'  => env('SITIO_FACEBOOK', ''),
        'youtube'   => env('SITIO_YOUTUBE', ''),
        'tiktok'    => env('SITIO_TIKTOK', ''),
        'linkedin'  => env('SITIO_LINKEDIN', ''),
    ]),

    /* ── Ficha de Google Business Profile (URL pública "g.page/..." o de Maps) ── */
    'google_maps' => env('SITIO_GOOGLE_MAPS', ''),

    /* ── Seguridad de transporte ── */
    // En producción toda petición http se redirige a https y se envía HSTS.
    'forzar_https' => (bool) env('SITIO_FORZAR_HTTPS', env('APP_ENV') === 'production'),
    'hsts_max_age' => (int) env('SITIO_HSTS_MAX_AGE', 31536000),

    /* ── Anti-spam de formularios públicos ── */
    'antispam' => [
        'campo_trampa'   => 'website',          // invisible para humanos
        'campo_tiempo'   => '_ts',              // marca de tiempo firmada
        'segundos_min'   => (int) env('SITIO_ANTISPAM_SEGUNDOS', 3),
        'vigencia_horas' => 6,
    ],
];
