@php
    use App\Models\Setting;
    use App\Support\CodigoQr;
    use App\Support\Formato;
    use Illuminate\Support\Facades\Storage;

    $cfg = Setting::todos();

    /* ── Formato del papel ──
       Sale de Configuración, pero la dirección lo puede cambiar (?f=58).
       Sirve para probar los tres anchos sin ir a Configuración cada vez, y
       para el caso real de tener dos impresoras: la del mostrador y la
       portátil del domiciliario. */
    $formatosValidos = ['80', '58', 'a4'];
    $formato = request()->query('f', $cfg['recibo.formato'] ?? '80');
    $formato = in_array($formato, $formatosValidos, true) ? $formato : '80';

    $copias = max(1, min(5, (int) ($cfg['recibo.copias'] ?? 1)));

    $logo = ! empty($cfg['negocio.logo']) && ($cfg['recibo.mostrar_logo'] ?? '1') === '1'
        ? Storage::url($cfg['negocio.logo'])
        : null;

    $verIcono  = ($cfg['recibo.mostrar_logo'] ?? '1') === '1' && ! $logo;
    $verCajero = ($cfg['recibo.mostrar_cajero'] ?? '1') === '1';
    $verAhorro = ($cfg['recibo.mostrar_ahorro'] ?? '1') === '1';
    $verQr     = ($cfg['recibo.mostrar_qr'] ?? '0') === '1';

    $enlace = route('sales.recibo', $sale);

    /* El QR se arma en el servidor y sin librerías: la caja tiene que poder
       imprimir aunque se haya caído internet. */
    $qr = null;

    if ($verQr) {
        try {
            $qr = CodigoQr::svg($enlace, $formato === '58' ? 82 : 96);
        } catch (\Throwable $e) {
            // Una dirección demasiado larga no puede tumbar el recibo:
            // el cliente necesita su papel más de lo que necesita el QR.
            $qr = null;
        }
    }

    /* ── Impuestos desglosados por tarifa ──
       Se agrupa por nombre + tarifa, no solo por tarifa: dos impuestos
       distintos pueden coincidir en el porcentaje y en el recibo tienen
       que salir con su propio nombre. */
    $desglose = collect();

    if (($cfg['recibo.mostrar_impuestos'] ?? '0') === '1') {
        $desglose = $sale->items
            ->filter(fn ($i) => (float) $i->tax_amount > 0)
            ->groupBy(fn ($i) => ($i->tax_name ?: 'IVA') . '|' . (float) $i->tax_rate)
            ->map(fn ($grupo, $clave) => [
                'nombre' => explode('|', $clave)[0],
                'tarifa' => (float) explode('|', $clave)[1],
                'base'   => $grupo->sum(fn ($i) => (float) $i->subtotal),
                'monto'  => $grupo->sum(fn ($i) => (float) $i->tax_amount),
            ])
            ->sortByDesc('tarifa')
            ->values();
    }

    $ahorro    = (float) $sale->discount_total;
    $unidades  = (float) $sale->items->sum('quantity');
    $recibido  = (float) $sale->payments->sum('amount');
    $negocio   = $cfg['negocio.nombre'];

    $textoWa = __('receipt.wa_texto', [
        'numero'  => $sale->number,
        'negocio' => $negocio,
        'total'   => Formato::moneda($sale->total),
        'enlace'  => $enlace,
    ]);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('receipt.recibo') }} {{ $sale->number }} — {{ $negocio }}</title>
    <style>
        /* ══════════════════════════════════════════════════════════════
           Recibo de venta.

           Tres anchos en una sola hoja de estilos, gobernados por una
           variable: --ancho. Tener tres plantillas separadas significaría
           corregir tres veces cada cambio, y la de 58 mm siempre sería la
           que se queda atrás.
           ══════════════════════════════════════════════════════════════ */
        :root{
            --ancho:302px;      /* 80 mm */
            --cuerpo:11.6px;
            --pad:20px 18px 24px;

            --tinta:#101825;
            --suave:#66768F;
            --tenue:#94A2B6;
            --linea:#C9D3E0;
            --linea-2:#E3E9F0;
            --marca:#215480;
            --fondo:#EEF2F7;
        }
        .f58{  --ancho:210px; --cuerpo:10.6px; --pad:14px 12px 18px; }
        .fa4{  --ancho:640px; --cuerpo:13px;   --pad:34px 38px 38px; }

        *{ box-sizing:border-box; }

        /* `minmax(0,1fr)` y no el `1fr` de siempre: `1fr` es en realidad
           `minmax(auto,1fr)`, y con eso la columna crece hasta el ancho de
           la tirilla —640 px en formato Carta— aunque la pantalla mida 390.
           El `max-width:100%` de la tirilla mediría contra esos 640 y no
           serviría de nada. Con minmax(0,1fr) la columna nunca pasa del
           ancho disponible y el recorte funciona. */
        body{ margin:0; padding:22px; background:var(--fondo);
              font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
              color:var(--tinta);
              display:grid; grid-template-columns:minmax(0,1fr);
              justify-items:center; align-items:start; }

        .tirilla{ width:var(--ancho); max-width:100%; padding:var(--pad);
                  background:#fff; border-radius:10px;
                  box-shadow:0 18px 44px -26px rgba(16,24,37,.5);
                  font-size:var(--cuerpo); line-height:1.55; }

        /* ── Cabecera ── */
        .cab{ text-align:center; padding-bottom:12px; border-bottom:1px dashed var(--linea); }
        .cab .logo{ display:grid; place-items:center; width:44px; height:44px; margin:0 auto 9px;
                    border-radius:11px; background:var(--marca); color:#fff; overflow:hidden;
                    position:relative; }
        /* Anclada a los cuatro lados: un logo muy alto o muy ancho se
           recorta y se centra, nunca se estira. */
        .cab .logo img{ position:absolute; inset:0; width:100%; height:100%;
                        object-fit:contain; background:#fff; }
        .cab h1{ margin:0; font-size:calc(var(--cuerpo) + 2.4px); font-weight:700; letter-spacing:.06em; }
        .cab p{ margin:2px 0 0; font-size:calc(var(--cuerpo) - 1.2px); color:var(--suave); }

        /* ── Datos de la venta ── */
        .meta{ padding:11px 0; border-bottom:1px dashed var(--linea); }
        .meta div{ display:flex; justify-content:space-between; gap:10px; }
        .meta span{ color:var(--suave); }
        .meta b{ font-weight:600; text-align:right; }

        /* ── Renglones ── */
        table{ width:100%; border-collapse:collapse; margin:11px 0; }
        thead th{ text-align:left; font-size:calc(var(--cuerpo) - 2px); letter-spacing:.06em;
                  text-transform:uppercase; color:var(--tenue); padding-bottom:5px;
                  border-bottom:1px solid var(--linea-2); font-weight:600; }
        thead th.n{ text-align:right; }
        tbody td{ padding:6px 0; vertical-align:top; border-bottom:1px solid #F2F5F9; }
        tbody td:first-child{ padding-right:10px; }
        tbody td.n{ text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
        .prod{ font-weight:600; }
        .cant{ color:var(--suave); font-size:calc(var(--cuerpo) - 1px); }

        /* ── Totales ── */
        .tot{ padding-top:9px; border-top:1px dashed var(--linea); }
        .tot div{ display:flex; justify-content:space-between; padding:2px 0; color:var(--suave); }
        .tot div b{ color:#33415A; font-variant-numeric:tabular-nums; }
        .tot .imp{ font-size:calc(var(--cuerpo) - 1px); padding-left:9px; }
        .tot .grande{ margin-top:6px; padding-top:7px; border-top:1px solid var(--linea-2);
                      font-size:calc(var(--cuerpo) + 2.4px); color:var(--tinta); }
        .tot .grande b{ font-size:calc(var(--cuerpo) + 7.4px); font-weight:700;
                        color:var(--marca); letter-spacing:-.02em; }

        .pagos{ margin-top:10px; padding-top:9px; border-top:1px dashed var(--linea); }
        .pagos div{ display:flex; justify-content:space-between; padding:2px 0; }
        .pagos b{ font-variant-numeric:tabular-nums; }

        .ahorro{ margin-top:10px; padding:7px 10px; border-radius:7px; text-align:center;
                 background:#EEF5F0; color:#2C6B4A; font-weight:700;
                 font-size:calc(var(--cuerpo) - .4px); }

        /* ── Pie y QR ── */
        .qr{ margin-top:13px; text-align:center; }
        .qr svg{ display:block; margin:0 auto; }
        .qr span{ display:block; margin-top:5px; font-size:calc(var(--cuerpo) - 2px); color:var(--tenue); }

        .pie{ margin-top:14px; padding-top:11px; border-top:1px dashed var(--linea);
              text-align:center; font-size:calc(var(--cuerpo) - 1.4px); color:var(--tenue); }
        .pie .msg{ color:var(--suave); font-weight:600;
                   font-size:calc(var(--cuerpo) - .2px); display:block; margin-bottom:3px; }

        .anulada{ margin:10px 0; padding:7px; text-align:center; border-radius:7px;
                  background:#F7EEEE; color:#96504F; font-weight:700;
                  letter-spacing:.08em; font-size:calc(var(--cuerpo) - 1.1px); }

        /* ── Carta / A4: dos columnas, que hay sitio ── */
        .fa4 .meta{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0 26px; }
        .fa4 .cab{ text-align:left; display:flex; align-items:center; gap:16px; }
        .fa4 .cab .logo{ margin:0; width:56px; height:56px; flex:none; }
        .fa4 .cab .datos{ min-width:0; }
        .fa4 .tot{ margin-left:auto; width:min(300px,100%); }
        .fa4 .qr{ text-align:right; }
        .fa4 .qr svg{ margin:0 0 0 auto; }

        /* ══════════ Barra de acciones ══════════ */
        /* La barra NO se ata al ancho de la tirilla: con 58 mm quedarían
           cuatro botones uno debajo de otro. Se le da su propio ancho
           cómodo y en celular ya se reparte sola. */
        .barra{ display:flex; gap:8px; flex-wrap:wrap; align-items:center;
                width:min(560px,100%); margin-bottom:16px; }
        .barra button, .barra a{ display:inline-flex; align-items:center; gap:7px;
                                 padding:9px 15px; border:0; border-radius:9px; font:inherit;
                                 font-size:12.6px; font-weight:600; cursor:pointer;
                                 text-decoration:none; line-height:1;
                                 transition:transform .16s ease, box-shadow .16s ease, background .16s ease; }
        .barra button:hover, .barra a:hover{ transform:translateY(-1px); }
        .barra .p{ background:var(--marca); color:#fff;
                   box-shadow:0 8px 20px -14px rgba(33,84,128,.9); }
        .barra .g{ background:#fff; color:#33415A; border:1px solid #DFE5EC; }
        .barra .wa{ background:#1F8A54; color:#fff; }
        .barra svg{ width:15px; height:15px; flex:none; }

        .anchos{ display:inline-flex; gap:2px; padding:3px; background:#fff;
                 border:1px solid #DFE5EC; border-radius:9px; }
        .anchos a{ padding:6px 10px; border-radius:7px; font-size:11.6px; color:var(--suave);
                   background:transparent; }
        .anchos a.on{ background:var(--marca); color:#fff; }

        .fa4 .barra{ width:min(640px,100%); }

        @media (max-width:460px){
            body{ padding:10px 8px; }
            .barra{ width:100%; }
            .barra button, .barra a:not(.anchos a){ flex:1 1 auto; justify-content:center; }
        }

        /* ══════════ Impresión ══════════ */
        @media print{
            /* El tamaño de página se declara aquí y no en el diálogo del
               navegador: así la tirilla sale del ancho del rollo y no
               centrada en una hoja carta con márgenes enormes. */
            @page{ margin:0; }

            body{ background:#fff; padding:0; display:block; }
            .tirilla{ width:auto; box-shadow:none; border-radius:0; padding:4mm 3mm; }
            .barra{ display:none; }

            /* Cada copia en su propia hoja. */
            .copia{ page-break-after:always; }
            .copia:last-child{ page-break-after:auto; }
        }
    </style>
</head>
<body class="f{{ $formato }}">

    {{-- ══════════ Acciones (nunca se imprimen) ══════════ --}}
    <div class="barra">
        <button type="button" class="p" onclick="imprimir()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M7 9V3.5h10V9"/><rect x="3" y="9" width="18" height="7.5" rx="2"/>
                <path d="M7 14h10v6.5H7z"/>
            </svg>
            {{ __('receipt.imprimir') }}
        </button>

        <a class="wa" target="_blank" rel="noopener"
           href="https://wa.me/?text={{ rawurlencode($textoWa) }}">
            <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2Zm5.8 14.2c-.2.7-1.2 1.3-2 1.4-.5.1-1.2.1-3.8-.9-3.2-1.3-5.2-4.5-5.4-4.7-.2-.2-1.3-1.7-1.3-3.2s.8-2.3 1.1-2.6c.3-.3.6-.4.8-.4h.6c.2 0 .5 0 .7.5l1 2.3c.1.2.1.4 0 .6l-.4.6-.3.3c-.1.2-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.1.5.1.6-.1l.9-1c.2-.2.4-.2.6-.1l2.2 1.1c.3.1.5.2.6.4.1.2.1.9-.1 1.6Z"/>
            </svg>
            {{ __('receipt.compartir') }}
        </a>

        <button type="button" class="g" onclick="copiarEnlace(this)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M10 13.5a4 4 0 0 0 5.7 0l2.8-2.8a4 4 0 1 0-5.7-5.7L11.5 6.3"/>
                <path d="M14 10.5a4 4 0 0 0-5.7 0l-2.8 2.8a4 4 0 1 0 5.7 5.7l1.3-1.3"/>
            </svg>
            <span data-copiar>{{ __('receipt.copiar_link') }}</span>
        </button>

        {{-- Cambia el ancho sin tocar Configuración. --}}
        <span class="anchos">
            @foreach (['80' => '80 mm', '58' => '58 mm', 'a4' => 'Carta'] as $k => $txt)
                <a href="{{ route('sales.recibo', [$sale, 'f' => $k]) }}"
                   class="{{ $formato === $k ? 'on' : '' }}">{{ $txt }}</a>
            @endforeach
        </span>

        <a class="g" href="{{ route('sales.show', $sale) }}">{{ __('receipt.volver') }}</a>
    </div>

    {{-- ══════════ La tirilla, repetida tantas veces como copias ══════════ --}}
    @for ($copia = 1; $copia <= $copias; $copia++)
    <div class="tirilla copia">
        <div class="cab">
            @if ($logo)
                <span class="logo"><img src="{{ $logo }}" alt=""></span>
            @elseif ($verIcono)
                <span class="logo">
                    <svg viewBox="0 0 48 48" width="24" height="24" fill="none" stroke="#fff"
                         stroke-width="3" stroke-linejoin="round" stroke-linecap="round">
                        <path d="M8 16 L24 7 L40 16 L24 25 Z"/>
                        <path d="M8 16 v16 L24 41 V25"/><path d="M40 16 v16 L24 41"/>
                    </svg>
                </span>
            @endif

            <div class="datos">
                <h1>{{ $negocio }}</h1>
                @if ($cfg['negocio.lema'])<p>{{ $cfg['negocio.lema'] }}</p>@endif
                @if ($cfg['negocio.nit'])<p>{{ __('receipt.nit') }} {{ $cfg['negocio.nit'] }}</p>@endif
                @if ($cfg['negocio.direccion'])
                    <p>{{ $cfg['negocio.direccion'] }}{{ $cfg['negocio.ciudad'] ? ' · ' . $cfg['negocio.ciudad'] : '' }}</p>
                @endif
                @if ($cfg['negocio.telefono'])<p>{{ __('receipt.tel') }} {{ $cfg['negocio.telefono'] }}</p>@endif
            </div>
        </div>

        @if ($sale->status === 'anulada')
            <div class="anulada">{{ __('receipt.anulada') }}</div>
        @endif

        <div class="meta">
            <div><span>{{ __('receipt.recibo') }}</span><b>{{ $sale->number }}</b></div>
            <div><span>{{ __('receipt.fecha') }}</span><b>@fechahora($sale->sold_at)</b></div>
            @if ($verCajero)
                <div><span>{{ __('receipt.atendio') }}</span><b>{{ $sale->user->name ?? '—' }}</b></div>
            @endif
            <div>
                <span>{{ __('receipt.cliente') }}</span>
                <b>{{ $sale->customer?->full_name ?? __('receipt.consumidor') }}</b>
            </div>
            @if ($sale->customer?->document_number)
                <div><span>{{ __('receipt.documento') }}</span><b>{{ $sale->customer->document }}</b></div>
            @endif
        </div>

        <table>
            <thead>
                <tr>
                    <th>{{ __('receipt.producto') }}</th>
                    <th class="n">{{ __('receipt.valor') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td>
                            <span class="prod">{{ $item->name }}</span><br>
                            <span class="cant">
                                @cantidad($item->quantity) × @dinero($item->unit_price)
                            </span>
                        </td>
                        <td class="n">@dinero($item->total)</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="tot">
            <div><span>{{ __('receipt.subtotal') }}</span><b>@dinero($sale->subtotal)</b></div>

            @if ($ahorro > 0)
                <div><span>{{ __('receipt.descuentos') }}</span><b>−@dinero($ahorro)</b></div>
            @endif

            @if ($desglose->isNotEmpty())
                @foreach ($desglose as $imp)
                    <div class="imp">
                        <span>{{ $imp['nombre'] }} @porcentaje($imp['tarifa'])</span>
                        <b>@dinero($imp['monto'])</b>
                    </div>
                @endforeach
            @else
                <div><span>{{ __('receipt.impuestos') }}</span><b>@dinero($sale->tax_total)</b></div>
            @endif

            <div class="grande"><span>{{ __('receipt.total') }}</span><b>@dinero($sale->total)</b></div>
        </div>

        <div class="pagos">
            @foreach ($sale->payments as $p)
                <div><span>{{ $p->method_name }}</span><b>@dinero($p->amount)</b></div>
            @endforeach

            @if ((float) $sale->change_amount > 0)
                <div><span>{{ __('receipt.cambio') }}</span><b>@dinero($sale->change_amount)</b></div>
            @endif
        </div>

        @if ($verAhorro && $ahorro > 0)
            <div class="ahorro">{{ __('receipt.ahorro') }} @dinero($ahorro)</div>
        @endif

        @if ($qr)
            <div class="qr">
                {!! $qr !!}
                <span>{{ __('receipt.qr_pie') }}</span>
            </div>
        @endif

        <div class="pie">
            <span class="msg">{{ $cfg['recibo.mensaje'] ?: __('receipt.gracias') }}</span>
            {{ __('receipt.resumen', [
                'unidades'    => Formato::cantidad($unidades),
                'referencias' => $sale->items->count(),
            ]) }}
            @if ($cfg['recibo.pie'])
                <br><br>{{ $cfg['recibo.pie'] }}
            @endif
        </div>
    </div>
    @endfor

    <script>
    /* ══════════════════════════════════════════════════════════════
       Tres cosas, y ninguna necesita librerías.
       ══════════════════════════════════════════════════════════════ */

    /* 1. Imprimir. Se llama así y no con window.print() directo porque en
          el celular hay que dar un respiro al navegador después de que la
          hoja de impresión se aplica, o sale la vista previa a medio armar. */
    function imprimir() {
        setTimeout(function () { window.print(); }, 60);
    }

    /* 2. Copiar el enlace del recibo. El portapapeles moderno solo funciona
          sobre HTTPS; en http:// —que es como corre Laragon en local— hay
          que caer al método viejo o el botón no hace nada y parece roto. */
    function copiarEnlace(boton) {
        var url  = @json($enlace);
        var span = boton.querySelector('[data-copiar]');
        var antes = span.textContent;

        function listo() {
            span.textContent = @json(__('receipt.copiado'));
            setTimeout(function () { span.textContent = antes; }, 1800);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(listo, respaldo);
        } else {
            respaldo();
        }

        function respaldo() {
            var t = document.createElement('textarea');
            t.value = url;
            t.style.position = 'fixed';
            t.style.opacity = '0';
            document.body.appendChild(t);
            t.select();
            try { document.execCommand('copy'); listo(); } catch (e) { /* nada que hacer */ }
            document.body.removeChild(t);
        }
    }

    /* 3. Impresión automática al llegar desde la caja (?imprimir=1). */
    (function () {
        var p = new URLSearchParams(window.location.search);
        if (p.get('imprimir') === '1') { imprimir(); }
    })();
    </script>
</body>
</html>
