{{--
    Landing pública de MEGA UNI STORE.

    ── Una sola llamada a la acción ──
    Todo lo que hay aquí empuja a lo mismo: «Probar gratis» → /probar.
    Aparece tres veces (encabezado, hero y cierre) pero es SIEMPRE la misma
    acción con el mismo texto. «Iniciar sesión» existe para el que ya es
    cliente, como enlace de texto, sin competir visualmente.

    La versión anterior (plantilla SICEFA, 1 695 líneas, recursos de
    sicefa.com.co, organigrama y Cefamaps ajenos) quedó en el historial de
    Git: `git show feature/smart-inventory-entry:resources/views/welcome.blade.php`.
--}}
@extends('sitio.layout')

@section('titulo', 'POS e inventario en la nube para tu negocio')
@section('descripcion', 'ERP y POS en la nube: vende con lector de códigos, controla inventario con kardex, cuadra caja con cierre Z y mira reportes. Pruébalo gratis 2 h 30, sin tarjeta.')

@php
    $duracion = intdiv($minutos, 60).' h '.str_pad((string) ($minutos % 60), 2, '0', STR_PAD_LEFT);
@endphp

@php
    /*
     * El arreglo se arma dentro de @php a propósito: escrito en el cuerpo
     * de la vista, Blade compila la clave '@context' como su directiva
     * @context(...) y el JSON-LD sale corrupto (lo detectó SitioPublicoTest).
     */
    $software = [
    '@context'            => 'https://schema.org',
    '@type'               => 'SoftwareApplication',
    'name'                => config('sitio.marca'),
    'applicationCategory' => 'BusinessApplication',
    'applicationSubCategory' => 'Punto de venta (POS) e inventario',
    'operatingSystem'     => 'Web',
    'inLanguage'          => 'es-CO',
    'url'                 => url('/'),
    'description'         => config('sitio.descripcion'),
    'image'               => asset('img/sitio/og-image.jpg'),
    'publisher'           => ['@id' => url('/').'#organizacion'],
    'offers'              => [
        '@type'         => 'Offer',
        'price'         => '0',
        'priceCurrency' => 'COP',
        'description'   => "Prueba gratuita de {$duracion} sin tarjeta",
        'url'           => route('demo.crear'),
    ],
    'featureList' => [
        'Punto de venta con lector de código de barras',
        'Inventario con kardex por tienda',
        'Caja con arqueo y cierre Z',
        'Compras y lectura de facturas con IA',
        'Reportes de ventas y utilidad',
        'Varias tiendas y traslados',
    ],
];
@endphp
@push('jsonld')
<script type="application/ld+json">{!! json_encode($software, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@push('estilos')
<style>
    /* ── Hero ── texto #fff y #C4D2E4 sobre #0A1119: 18:1 y 12:1 */
    .hero{background:
            radial-gradient(900px 500px at 85% 10%, rgba(46,110,168,.35), transparent 60%),
            linear-gradient(165deg,var(--n-900) 0%,var(--n-850) 55%,var(--n-800) 100%);
          color:#fff;padding:72px 0 88px}
    .hero__grid{display:grid;gap:48px;align-items:center;grid-template-columns:1.1fr .9fr}
    .hero__etq{display:inline-block;background:rgba(127,178,222,.14);color:#C4D2E4;border:1px solid rgba(127,178,222,.3);
               padding:6px 14px;border-radius:999px;font-size:.875rem;font-weight:600;margin:0 0 18px}
    .hero h1{font-size:clamp(2rem,4.6vw,3.2rem);line-height:1.12;letter-spacing:-.02em;margin:0 0 18px}
    .hero h1 span{color:var(--a-300)}
    .hero__sub{font-size:clamp(1.05rem,1.8vw,1.2rem);color:#C4D2E4;max-width:560px;margin:0 0 28px}
    .hero__notas{list-style:none;padding:0;margin:18px 0 0;display:flex;flex-wrap:wrap;gap:8px 20px;color:#C4D2E4;font-size:.95rem}
    .hero__notas li::before{content:"✓";color:#7FD3A6;font-weight:800;margin-right:6px}
    .btn--grande{min-height:56px;padding:14px 32px;font-size:1.1rem}

    /* Ilustración: tirilla de venta hecha con HTML (0 KB de imagen) */
    .ticket{background:#fff;color:var(--tx);border-radius:18px;padding:22px;box-shadow:0 30px 60px rgba(0,0,0,.35);max-width:380px;margin-left:auto}
    .ticket__cab{display:flex;justify-content:space-between;align-items:center;border-bottom:1px dashed var(--borde);padding-bottom:12px;margin-bottom:12px}
    .ticket__cab b{font-size:.95rem}
    .ticket__cab small{color:var(--tx-2)}
    .ticket__fila{display:flex;justify-content:space-between;gap:12px;padding:6px 0;font-size:.95rem}
    .ticket__fila span:first-child{color:var(--tx-2)}
    .ticket__total{display:flex;justify-content:space-between;border-top:2px solid var(--tx);margin-top:10px;padding-top:12px;font-weight:800;font-size:1.15rem}
    .ticket__pago{margin-top:14px;display:flex;gap:8px;flex-wrap:wrap}
    .ticket__pago span{background:var(--claro);border:1px solid var(--borde);border-radius:999px;padding:4px 12px;font-size:.85rem;color:var(--tx-2)}
    .ticket__ok{margin-top:14px;background:#E8F5EE;color:var(--ok);border-radius:10px;padding:10px 12px;font-weight:700;font-size:.9rem}

    /* ── Secciones ── */
    .sec{padding:80px 0}
    .sec--claro{background:var(--claro)}
    .sec__cab{max-width:680px;margin:0 auto 44px;text-align:center}
    .sec__cab h2{font-size:clamp(1.6rem,3.2vw,2.3rem);line-height:1.2;margin:0 0 12px;letter-spacing:-.01em}
    .sec__cab p{color:var(--tx-2);margin:0;font-size:1.05rem}

    .rejilla{display:grid;gap:20px;grid-template-columns:repeat(auto-fit,minmax(300px,1fr))}
    .tarjeta{background:#fff;border:1px solid var(--borde);border-radius:var(--radio);padding:24px}
    .tarjeta h3{margin:12px 0 6px;font-size:1.1rem}
    .tarjeta p{margin:0;color:var(--tx-2)}
    .ico{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:#E7F0F9;color:var(--a-600)}
    .ico svg{width:24px;height:24px}

    .rubros{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(165px,1fr))}
    .rubro{background:#fff;border:1px solid var(--borde);border-radius:12px;padding:18px}
    .rubro b{display:block;margin-bottom:4px}
    .rubro span{color:var(--tx-2);font-size:.95rem}

    .pasos{counter-reset:paso;display:grid;gap:20px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));list-style:none;padding:0;margin:0}
    .pasos li{counter-increment:paso;background:#fff;border:1px solid var(--borde);border-radius:var(--radio);padding:24px 24px 24px 72px;position:relative}
    .pasos li::before{content:counter(paso);position:absolute;left:22px;top:22px;width:36px;height:36px;border-radius:50%;
                      background:var(--a-500);color:#fff;font-weight:800;display:grid;place-items:center}
    .pasos h3{margin:0 0 6px;font-size:1.05rem}
    .pasos p{margin:0;color:var(--tx-2)}

    .faq{max-width:780px;margin:0 auto;display:grid;gap:12px}
    .faq details{background:#fff;border:1px solid var(--borde);border-radius:12px;padding:0 20px}
    .faq summary{cursor:pointer;font-weight:700;padding:18px 0;list-style:none;display:flex;justify-content:space-between;gap:12px}
    .faq summary::-webkit-details-marker{display:none}
    .faq summary::after{content:"+";font-size:1.4rem;line-height:1;color:var(--a-600)}
    .faq details[open] summary::after{content:"–"}
    .faq details p{margin:0 0 18px;color:var(--tx-2)}

    .cierre{background:linear-gradient(135deg,var(--a-700),var(--a-500));color:#fff;text-align:center;padding:72px 0}
    .cierre h2{font-size:clamp(1.6rem,3.2vw,2.3rem);margin:0 0 12px}
    .cierre p{color:#E3EEF8;margin:0 auto 28px;max-width:560px;font-size:1.05rem}
    /* Sobre fondo azul el botón se invierte para no perder contraste */
    .cierre .btn--cta{background:#fff;color:var(--a-700);box-shadow:0 10px 30px rgba(0,0,0,.25)}
    .cierre .btn--cta:hover{background:#EAF2FA;color:var(--a-700)}

    @media (max-width:880px){
        .hero{padding:48px 0 64px}
        .hero__grid{grid-template-columns:1fr}
        .ticket{margin:0 auto}
        .hero__ilus{display:none} /* en móvil la CTA queda arriba del pliegue */
        .sec{padding:60px 0}
    }
</style>
@endpush

@section('contenido')

{{-- ═════════════ Hero ═════════════ --}}
<section class="hero" aria-labelledby="hero-titulo">
    <div class="contenedor hero__grid">
        <div>
            <p class="hero__etq">ERP + punto de venta en la nube</p>
            <h1 id="hero-titulo">Vende, controla tu inventario y cuadra la caja <span>desde un solo sistema</span></h1>
            <p class="hero__sub">
                Punto de venta con lector de códigos, kardex por tienda, compras, cierre Z y reportes de utilidad.
                Pensado para negocios en Colombia, con precios en pesos y tu propio catálogo listo desde el primer minuto.
            </p>
            @auth
                <a class="btn btn--cta btn--grande" href="{{ route('dashboard') }}">Ir a mi panel</a>
            @else
                <a class="btn btn--cta btn--grande" href="{{ route('demo.crear') }}">Probar gratis {{ $duracion }}</a>
            @endauth
            <ul class="hero__notas">
                <li>Sin tarjeta</li>
                <li>Entras en un minuto</li>
                <li>Con productos de tu tipo de negocio</li>
            </ul>
        </div>

        <div class="hero__ilus" aria-hidden="true">
            <div class="ticket">
                <div class="ticket__cab"><b>Venta #1024</b><small>Caja 1 · 10:42</small></div>
                <div class="ticket__fila"><span>2 × Cemento gris 50 kg</span><span>$64.000</span></div>
                <div class="ticket__fila"><span>1 × Pala punta redonda</span><span>$38.500</span></div>
                <div class="ticket__fila"><span>3 × Lija N.º 100</span><span>$4.500</span></div>
                <div class="ticket__total"><span>Total</span><span>$107.000</span></div>
                <div class="ticket__pago"><span>Efectivo $60.000</span><span>Nequi $47.000</span></div>
                <div class="ticket__ok">Inventario y kardex actualizados</div>
            </div>
        </div>
    </div>
</section>

{{-- ═════════════ Rubros ═════════════ --}}
<section class="sec sec--claro" aria-labelledby="rubros-titulo">
    <div class="contenedor">
        <div class="sec__cab">
            <h2 id="rubros-titulo">Arranca con el catálogo de tu negocio</h2>
            <p>Escoges tu tipo de negocio y la prueba se crea con sus productos, unidades y cargos. Nada de pantallas vacías.</p>
        </div>
        <div class="rubros">
            @foreach ($rubros as [$nombre, $descripcion])
                <div class="rubro"><b>{{ $nombre }}</b><span>{{ $descripcion }}</span></div>
            @endforeach
        </div>
    </div>
</section>

{{-- ═════════════ Funciones ═════════════ --}}
<section class="sec" id="funciones" aria-labelledby="funciones-titulo">
    <div class="contenedor">
        <div class="sec__cab">
            <h2 id="funciones-titulo">Todo lo que pasa en tu negocio, conectado</h2>
            <p>Cada venta descuenta el inventario, cada compra actualiza el costo y cada peso queda en el cierre de caja.</p>
        </div>
        <div class="rejilla">
            <article class="tarjeta">
                <div class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M7 20h10M12 16v4"/></svg></div>
                <h3>Punto de venta rápido</h3>
                <p>Busca por nombre, SKU o código de barras con lector, cobra con varios medios a la vez y calcula el cambio. Atajos F2 y F9.</p>
            </article>
            <article class="tarjeta">
                <div class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5Z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg></div>
                <h3>Inventario con kardex</h3>
                <p>Existencias por tienda, saldo corriendo producto por producto, ajustes con motivo y avisos de lo que se está agotando.</p>
            </article>
            <article class="tarjeta">
                <div class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="13" rx="2"/><path d="M2 10h20M6 15h4"/></svg></div>
                <h3>Caja y cierre Z</h3>
                <p>Turnos con base inicial, arqueo de lo esperado contra lo contado, desglose por medio de pago y cierre Z imprimible.</p>
            </article>
            <article class="tarjeta">
                <div class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg></div>
                <h3>Compras con lectura de facturas por IA</h3>
                <p>Toma una foto de la factura del proveedor y el sistema propone productos, cantidades y costos para que los revises antes de cargarlos.</p>
            </article>
            <article class="tarjeta">
                <div class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/></svg></div>
                <h3>Reportes que se entienden</h3>
                <p>Ventas por día, lo más vendido, utilidad, inventario valorizado, desempeño por vendedor y productos sin rotación.</p>
            </article>
            <article class="tarjeta">
                <div class="ico" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9 5 4h14l2 5M4 9v11h16V9M9 20v-6h6v6"/></svg></div>
                <h3>Varias tiendas y roles</h3>
                <p>Traslados entre locales, permisos por cargo (cajero, bodeguero, supervisor) y auditoría de quién hizo qué.</p>
            </article>
        </div>
    </div>
</section>

{{-- ═════════════ Cómo funciona ═════════════ --}}
<section class="sec sec--claro" aria-labelledby="pasos-titulo">
    <div class="contenedor">
        <div class="sec__cab">
            <h2 id="pasos-titulo">Pruébalo con tu negocio en tres pasos</h2>
        </div>
        <ol class="pasos">
            <li><h3>Cuéntanos qué negocio tienes</h3><p>Nombre, correo y tipo de negocio. Nada más.</p></li>
            <li><h3>Entra a tu sistema ya armado</h3><p>Con productos, existencias y cargos de tu rubro, listos para vender.</p></li>
            <li><h3>Haz tu primera venta</h3><p>Cobra, mira cómo baja el inventario y cierra la caja. Tienes {{ $duracion }}.</p></li>
        </ol>
    </div>
</section>

{{-- ═════════════ Preguntas ═════════════ --}}
<section class="sec" id="preguntas" aria-labelledby="faq-titulo">
    <div class="contenedor">
        <div class="sec__cab">
            <h2 id="faq-titulo">Preguntas frecuentes</h2>
        </div>
        <div class="faq">
            <details>
                <summary>¿La prueba tiene costo o me piden tarjeta?</summary>
                <p>No. La prueba dura {{ $duracion }}, es gratis y no pide ningún medio de pago.</p>
            </details>
            <details>
                <summary>¿Qué pasa con mis datos cuando termina la prueba?</summary>
                <p>La cuenta de prueba se elimina sola tres días después de vencer, con todo lo que cargaste. Lo explicamos en la <a href="{{ route('sitio.privacidad') }}">política de privacidad</a>.</p>
            </details>
            <details>
                <summary>¿Necesito instalar algo?</summary>
                <p>No. Funciona en el navegador, sin instalaciones. Para el mostrador puedes conectar un lector de códigos de barras, una impresora de tirilla y un cajón monedero.</p>
            </details>
            <details>
                <summary>¿Puedo manejar varias sedes?</summary>
                <p>Sí. Cada tienda lleva sus propias existencias y puedes trasladar mercancía entre ellas dejando el registro en el kardex.</p>
            </details>
            <details>
                <summary>¿Otras empresas pueden ver mi información?</summary>
                <p>No. Cada empresa trabaja aislada y solo ve sus propios datos; además, cada usuario solo entra a lo que su cargo le permite.</p>
            </details>
        </div>
    </div>
</section>

{{-- ═════════════ Cierre: la misma y única acción ═════════════ --}}
<section class="cierre" aria-labelledby="cierre-titulo">
    <div class="contenedor">
        <h2 id="cierre-titulo">Haz tu primera venta hoy</h2>
        <p>En un minuto tienes tu negocio montado. Si te sirve, seguimos; si no, no pierdes nada.</p>
        @auth
            <a class="btn btn--cta btn--grande" href="{{ route('dashboard') }}">Ir a mi panel</a>
        @else
            <a class="btn btn--cta btn--grande" href="{{ route('demo.crear') }}">Probar gratis {{ $duracion }}</a>
        @endauth
    </div>
</section>

@endsection
