{{--
    Layout del sitio público (landing + páginas legales).

    Autocontenido a propósito: CSS en línea, cero frameworks, cero fuentes
    externas, cero iconos por CDN. La landing anterior pedía 14 recursos a
    3 dominios (uno de ellos ajeno, sicefa.com.co); esta pide el HTML, el
    logo y —solo si el visitante acepta— Google Analytics.

    Secciones que puede definir cada página:
      @section('titulo')       <title> (sin la marca; se agrega sola)
      @section('descripcion')  meta description (150–160 caracteres)
      @section('og_tipo')      website | article
      @push('jsonld')          bloques <script type="application/ld+json">
      @push('estilos')         CSS propio de la página
--}}
@php
    $s       = config('sitio');
    $marca   = $s['marca'];
    $titulo  = trim($__env->yieldContent('titulo'));
    $tituloCompleto = $titulo !== '' ? "{$titulo} · {$marca}" : "{$marca} — {$s['eslogan']}";
    $desc    = trim($__env->yieldContent('descripcion')) ?: $s['descripcion'];
    $canonico = url()->current();
    $wa      = $s['whatsapp'];
@endphp
<!DOCTYPE html>
<html lang="es-CO">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tituloCompleto }}</title>
    <meta name="description" content="{{ $desc }}">
    <link rel="canonical" href="{{ $canonico }}">
    <meta name="theme-color" content="#0E1723">
    @unless (app()->isProduction())
        <meta name="robots" content="noindex, nofollow">
    @endunless

    {{-- Open Graph / redes: la vista previa al compartir el enlace por WhatsApp o redes. --}}
    <meta property="og:type" content="@yield('og_tipo', 'website')">
    <meta property="og:site_name" content="{{ $marca }}">
    <meta property="og:locale" content="es_CO">
    <meta property="og:title" content="{{ $tituloCompleto }}">
    <meta property="og:description" content="{{ $desc }}">
    <meta property="og:url" content="{{ $canonico }}">
    <meta property="og:image" content="{{ asset('img/sitio/og-image.jpg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Logo de {{ $marca }}">
    <meta name="twitter:card" content="summary_large_image">

    @include('sitio.partials.iconos')
    @include('sitio.partials.jsonld-base')
    @stack('jsonld')

    <style>
        *,*::before,*::after{box-sizing:border-box}
        :root{
            --n-950:#070D14;--n-900:#0A1119;--n-850:#0E1723;--n-800:#142033;
            --a-700:#1B4669;--a-600:#215480;--a-500:#2E6EA8;--a-300:#7FB2DE;
            --tx:#0E1723;--tx-2:#3F4F65;--claro:#F5F8FC;--borde:#DCE4EE;
            --ok:#1F7A4D;--radio:14px;
            --sombra:0 10px 30px rgba(10,17,25,.10);
            --fuente:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;
        }
        html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
        body{margin:0;font-family:var(--fuente);color:var(--tx);background:#fff;line-height:1.6;font-size:16px}
        img,svg,video{max-width:100%;height:auto;display:block}
        a{color:var(--a-600)}
        a:hover{color:var(--a-700)}
        :focus-visible{outline:3px solid var(--a-300);outline-offset:2px;border-radius:4px}
        .contenedor{width:100%;max-width:1140px;margin:0 auto;padding:0 20px}
        .salto{position:absolute;left:-9999px;top:8px;background:#fff;color:var(--tx);padding:8px 14px;border-radius:8px;z-index:100}
        .salto:focus{left:8px}
        .vh{position:absolute!important;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}

        /* Botón principal: blanco sobre #2E6EA8 = 5,3:1 (AA). */
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:48px;padding:12px 24px;
             border-radius:999px;font-weight:700;font-size:1rem;text-decoration:none;border:0;cursor:pointer;
             transition:transform .15s ease, background-color .15s ease}
        .btn--cta{background:var(--a-500);color:#fff;box-shadow:0 8px 24px rgba(46,110,168,.35)}
        .btn--cta:hover{background:var(--a-600);color:#fff;transform:translateY(-1px)}
        .btn--sm{min-height:40px;padding:8px 18px;font-size:.95rem}

        /* Encabezado */
        .cab{position:sticky;top:0;z-index:50;background:rgba(10,17,25,.96);border-bottom:1px solid rgba(127,178,222,.18)}
        .cab__fila{display:flex;align-items:center;gap:16px;min-height:64px}
        .marca{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;font-weight:800;letter-spacing:.02em}
        .marca:hover{color:#fff}
        .marca img{width:36px;height:36px;border-radius:50%;background:#fff;padding:2px}
        .cab__nav{margin-left:auto;display:flex;align-items:center;gap:20px}
        .cab__nav a.l{color:#C4D2E4;text-decoration:none;font-weight:500;font-size:.95rem}
        .cab__nav a.l:hover{color:#fff}
        .cab__nav a{white-space:nowrap}
        @media (max-width:760px){ .cab__nav .solo-escritorio{display:none} .cab__nav{gap:14px} }
        @media (max-width:430px){ .marca span{display:none} .cab__nav a.l{font-size:.9rem} }

        /* Pie */
        .pie{background:var(--n-900);color:#C4D2E4;padding:48px 0 24px;font-size:.95rem}
        .pie a{color:#C4D2E4}
        .pie a:hover{color:#fff}
        .pie__grid{display:grid;gap:32px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
        .pie h2{color:#fff;font-size:1rem;margin:0 0 12px}
        .pie ul{list-style:none;margin:0;padding:0;display:grid;gap:8px}
        .pie__base{border-top:1px solid rgba(127,178,222,.18);margin-top:32px;padding-top:20px;display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between;color:#93A6BF;font-size:.875rem}
        .pie__base a{color:#93A6BF}
        .pie button.enlace{background:none;border:0;padding:0;color:#C4D2E4;font:inherit;text-decoration:underline;cursor:pointer}

        @media (prefers-reduced-motion:reduce){ *{scroll-behavior:auto!important;transition:none!important;animation:none!important} }
    </style>
    @stack('estilos')
</head>
<body>
    <a class="salto" href="#contenido">Saltar al contenido</a>

    <header class="cab">
        <div class="contenedor cab__fila">
            <a class="marca" href="{{ route('inicio') }}" aria-label="{{ $marca }}, ir al inicio">
                <img src="{{ asset('img/sitio/logo-96.webp') }}" alt="" width="36" height="36">
                <span>{{ $marca }}</span>
            </a>
            <nav class="cab__nav" aria-label="Principal">
                @if (request()->routeIs('inicio'))
                    <a class="l solo-escritorio" href="#funciones">Funciones</a>
                    <a class="l solo-escritorio" href="#preguntas">Preguntas</a>
                @endif
                @auth
                    <a class="l" href="{{ route('dashboard') }}">Ir al panel</a>
                @else
                    <a class="l" href="{{ route('login') }}">Iniciar sesión</a>
                    <a class="btn btn--cta btn--sm" href="{{ route('demo.crear') }}">Probar gratis</a>
                @endauth
            </nav>
        </div>
    </header>

    <main id="contenido">
        @yield('contenido')
    </main>

    <footer class="pie">
        <div class="contenedor">
            <div class="pie__grid">
                <div>
                    <h2>{{ $marca }}</h2>
                    <p style="margin:0">{{ $s['eslogan'] }}.</p>
                </div>

                <div>
                    <h2>Contacto</h2>
                    <ul>
                        @if ($s['legal']['email'])
                            <li><a href="mailto:{{ $s['legal']['email'] }}">{{ $s['legal']['email'] }}</a></li>
                        @endif
                        @if ($s['legal']['telefono'])
                            <li><a href="tel:{{ preg_replace('/[^\d+]/', '', $s['legal']['telefono']) }}">{{ $s['legal']['telefono'] }}</a></li>
                        @endif
                        @if ($wa)
                            <li><a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($s['whatsapp_mensaje']) }}" rel="noopener" target="_blank">WhatsApp</a></li>
                        @endif
                        <li>
                            <address style="font-style:normal">
                                @if ($s['legal']['direccion']){{ $s['legal']['direccion'] }}, @endif
                                {{ $s['legal']['ciudad'] }}, {{ $s['legal']['departamento'] }}, Colombia
                            </address>
                        </li>
                        @if ($s['google_maps'])
                            <li><a href="{{ $s['google_maps'] }}" rel="noopener" target="_blank">Ver en Google Maps</a></li>
                        @endif
                    </ul>
                </div>

                <div>
                    <h2>Legal</h2>
                    <ul>
                        <li><a href="{{ route('sitio.aviso') }}">Aviso legal</a></li>
                        <li><a href="{{ route('sitio.privacidad') }}">Política de privacidad</a></li>
                        <li><a href="{{ route('sitio.cookies') }}">Política de cookies</a></li>
                        <li><button type="button" class="enlace" data-cookies-abrir>Configurar cookies</button></li>
                    </ul>
                </div>

                @if (count($s['redes']))
                    <div>
                        <h2>Síguenos</h2>
                        <ul>
                            @foreach ($s['redes'] as $red => $url)
                                <li><a href="{{ $url }}" rel="noopener me" target="_blank">{{ ucfirst($red) }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="pie__base">
                <span>© {{ date('Y') }} {{ $s['legal']['razon_social'] ?: $marca }}@if ($s['legal']['nit']) · NIT {{ $s['legal']['nit'] }}@endif</span>
                <span>Hecho en {{ $s['legal']['departamento'] }}, Colombia</span>
            </div>
        </div>
    </footer>

    @include('sitio.partials.whatsapp')
    @include('sitio.partials.cookies')
</body>
</html>
