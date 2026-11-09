{{--
    Datos estructurados comunes a todo el sitio público (schema.org).

    - Organization / LocalBusiness: alimenta el panel de conocimiento de
      Google y debe coincidir EXACTO con la ficha de Google Business Profile
      (mismo nombre, dirección y teléfono → NAP consistente).
    - WebSite: le dice a Google cuál es el nombre del sitio.

    Validar en https://search.google.com/test/rich-results tras desplegar.
--}}
@php
    $s = config('sitio');
    $l = $s['legal'];

    $direccion = array_filter([
        '@type'           => 'PostalAddress',
        'streetAddress'   => $l['direccion'] ?: null,
        'addressLocality' => $l['ciudad'] ?: null,
        'addressRegion'   => $l['departamento'] ?: null,
        'postalCode'      => $l['codigo_postal'] ?: null,
        'addressCountry'  => $l['pais'] ?: null,
    ]);

    // Con dirección física es negocio local (aparece en Maps); sin ella, organización.
    $tipo = $l['direccion'] ? ['Organization', 'LocalBusiness'] : 'Organization';

    $organizacion = array_filter([
        '@context'  => 'https://schema.org',
        '@type'     => $tipo,
        '@id'       => url('/').'#organizacion',
        'name'      => $s['marca'],
        'legalName' => $l['razon_social'] ?: null,
        'taxID'     => $l['nit'] ?: null,
        'url'       => url('/'),
        'logo'      => asset('img/sitio/icon-512.png'),
        'image'     => asset('img/sitio/og-image.jpg'),
        'email'     => $l['email'] ?: null,
        'telephone' => $l['telefono'] ?: null,
        'address'   => count($direccion) > 2 ? $direccion : null,
        'hasMap'    => $s['google_maps'] ?: null,
        'sameAs'    => array_values($s['redes']) ?: null,
    ]);

    $sitio = [
        '@context'   => 'https://schema.org',
        '@type'      => 'WebSite',
        '@id'        => url('/').'#sitio',
        'name'       => $s['marca'],
        'url'        => url('/'),
        'inLanguage' => 'es-CO',
        'publisher'  => ['@id' => url('/').'#organizacion'],
    ];

    $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;
@endphp
<script type="application/ld+json">{!! json_encode($organizacion, $flags) !!}</script>
<script type="application/ld+json">{!! json_encode($sitio, $flags) !!}</script>
