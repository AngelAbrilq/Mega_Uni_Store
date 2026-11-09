{{--
    Envoltura común de las páginas legales: ancho de lectura, índice y
    fecha de vigencia. Cada página define @section('legal') con su texto.

    ⚠ Plantilla redactada a partir de la Ley 1581 de 2012, el Decreto 1377
    de 2013 (compilado en el DUR 1074 de 2015) y la Ley 527 de 1999. Debe
    revisarla un abogado antes de publicar en producción.
--}}
@extends('sitio.layout')

@push('estilos')
<style>
    .legal{padding:48px 0 72px;background:var(--claro)}
    .legal__doc{max-width:780px;margin:0 auto;background:#fff;border:1px solid var(--borde);border-radius:var(--radio);padding:40px clamp(20px,5vw,48px)}
    .legal h1{font-size:clamp(1.7rem,3.5vw,2.2rem);line-height:1.2;margin:0 0 8px}
    .legal h2{font-size:1.2rem;margin:32px 0 8px}
    .legal p,.legal li{color:var(--tx-2)}
    .legal table{width:100%;border-collapse:collapse;font-size:.92rem;margin:12px 0}
    .legal th,.legal td{border:1px solid var(--borde);padding:8px 10px;text-align:left;vertical-align:top}
    .legal th{background:var(--claro);color:var(--tx)}
    .legal .tabla{overflow-x:auto}
    .legal__vig{color:var(--tx-2);font-size:.9rem;margin:0 0 24px}
    .legal__pend{background:#FFF7E6;border:1px solid #F2C46D;color:#5C4200;border-radius:10px;padding:10px 14px;font-size:.9rem}
</style>
@endpush

@section('contenido')
    @php
        $l = config('sitio.legal');
        $responsable = $l['razon_social'] ?: config('sitio.marca');
        $faltan = ! $l['razon_social'] || ! $l['nit'] || ! $l['email'];
    @endphp
    <div class="legal">
        <div class="contenedor">
            <article class="legal__doc">
                @if ($faltan && ! app()->isProduction())
                    <p class="legal__pend" role="note">
                        Faltan datos del responsable en <code>.env</code> (SITIO_RAZON_SOCIAL, SITIO_NIT, SITIO_EMAIL).
                        Este aviso solo se ve fuera de producción.
                    </p>
                @endif
                @yield('legal')
                <p class="legal__vig" style="margin-top:40px">
                    Última actualización: {{ \Illuminate\Support\Carbon::parse($l['vigencia'])->translatedFormat('j \d\e F \d\e Y') }}.
                </p>
            </article>
        </div>
    </div>
@endsection
