@php
    $cfg      = \App\Models\Setting::todos();
    $dif      = (float) $cash->difference;
    $abierta  = $cash->status === 'abierta';
    $totalV   = (float) $ventas->sum('total');
    $unidades = 0;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('mus.caja.z_titulo', ['n' => $cash->id, 'negocio' => $cfg['negocio.nombre']]) }}</title>
    <style>
        *{ box-sizing:border-box; }
        body{ margin:0; padding:22px; background:#EEF2F7;
              font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
              color:#101825; display:grid; place-items:start center; }

        .tirilla{ width:330px; max-width:100%; padding:20px 18px 26px; background:#fff; border-radius:10px;
                  box-shadow:0 18px 44px -26px rgba(16,24,37,.5); font-size:11.6px; line-height:1.55; }

        .cab{ text-align:center; padding-bottom:12px; border-bottom:1px dashed #C9D3E0; }
        .cab .logo{ display:grid; place-items:center; width:40px; height:40px; margin:0 auto 8px;
                    border-radius:11px; background:#215480; color:#fff; }
        .cab h1{ margin:0; font-size:14px; font-weight:700; letter-spacing:.06em; }
        .cab p{ margin:2px 0 0; font-size:10.4px; color:#66768F; }
        .cab .tipo{ display:inline-block; margin-top:9px; padding:4px 12px; border-radius:6px;
                    background:#0E1723; color:#fff; font-size:11px; font-weight:700;
                    letter-spacing:.14em; }

        .sec{ margin-top:12px; padding-top:11px; border-top:1px dashed #C9D3E0; }
        .sec h2{ margin:0 0 7px; font-size:10px; font-weight:700; letter-spacing:.1em;
                 text-transform:uppercase; color:#94A2B6; }
        .fila{ display:flex; justify-content:space-between; gap:10px; padding:2px 0; }
        .fila span{ color:#66768F; }
        .fila b{ font-weight:600; font-variant-numeric:tabular-nums; }
        .fila.tot{ margin-top:6px; padding-top:7px; border-top:1px solid #E3E9F0;
                   font-size:13px; color:#101825; }
        .fila.tot b{ font-size:16px; font-weight:700; color:#215480; }

        .caja{ margin-top:10px; padding:11px 12px; border-radius:9px; background:#F4F7FA; }
        .caja .fila b{ color:#33415A; }
        .dif{ margin-top:8px; padding:9px; text-align:center; border-radius:8px; font-weight:700; }
        .dif.ok{ background:#EDF5F0; color:#2F6047; }
        .dif.sobra{ background:#EEF3F9; color:#215480; }
        .dif.falta{ background:#F7EEEE; color:#96504F; }

        table{ width:100%; border-collapse:collapse; margin-top:4px; }
        td{ padding:4px 0; vertical-align:top; }
        td.n{ text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
        .nom{ font-weight:600; }
        .sub{ color:#94A2B6; font-size:10.4px; }

        .firma{ margin-top:26px; padding-top:9px; border-top:1px solid #33415A; text-align:center;
                font-size:10.4px; color:#66768F; }
        .firma + .firma{ margin-top:34px; }

        .pie{ margin-top:16px; padding-top:11px; border-top:1px dashed #C9D3E0;
              text-align:center; font-size:10.2px; color:#94A2B6; }

        .aviso{ margin-top:10px; padding:8px; text-align:center; border-radius:7px;
                background:#FBF4E9; color:#8A6733; font-size:10.6px; font-weight:600; }

        .barra{ display:flex; gap:8px; margin-bottom:16px; }
        .barra button, .barra a{ display:inline-flex; align-items:center; gap:7px; padding:9px 16px;
                                 border:0; border-radius:9px; font:inherit; font-size:12.6px;
                                 font-weight:600; cursor:pointer; text-decoration:none; }
        .barra .p{ background:#215480; color:#fff; }
        .barra .g{ background:#fff; color:#33415A; border:1px solid #DFE5EC; }

        @media (max-width:420px){ body{ padding:10px 8px; } }

        @media print{
            body{ background:#fff; padding:0; display:block; }
            .tirilla{ width:auto; box-shadow:none; border-radius:0; padding:0; }
            .barra{ display:none; }
        }
    </style>
</head>
<body>
    <div class="barra">
        <button type="button" class="p" onclick="window.print()">{{ __('mus.acciones.imprimir') }}</button>
        <a class="g" href="{{ route('cash.show', $cash) }}">{{ __('mus.acciones.volver') }}</a>
    </div>

    <div class="tirilla">
        <div class="cab">
            <span class="logo">
                <svg viewBox="0 0 48 48" width="22" height="22" fill="none" stroke="#fff"
                     stroke-width="3" stroke-linejoin="round" stroke-linecap="round">
                    <path d="M8 16 L24 7 L40 16 L24 25 Z"/>
                    <path d="M8 16 v16 L24 41 V25"/><path d="M40 16 v16 L24 41"/>
                </svg>
            </span>
            <h1>{{ $cfg['negocio.nombre'] }}</h1>
            @if ($cfg['negocio.nit'])<p>{{ __('mus.campos.nit') }} {{ $cfg['negocio.nit'] }}</p>@endif
            @if ($cfg['negocio.direccion'])<p>{{ $cfg['negocio.direccion'] }}</p>@endif
            <span class="tipo">{{ __('mus.caja.z_encabezado') }}</span>
        </div>

        @if ($abierta)
            <div class="aviso">{{ __('mus.caja.z_provisional') }}</div>
        @endif

        <div class="sec">
            <h2>{{ __('mus.caja.z_turno') }}</h2>
            <div class="fila"><span>{{ __('mus.ventas.numero') }}</span><b>#{{ $cash->id }}</b></div>
            <div class="fila"><span>{{ __('mus.caja.responsable') }}</span><b>{{ $cash->user->name ?? '—' }}</b></div>
            <div class="fila"><span>{{ __('mus.caja.apertura') }}</span><b>{{ $cash->opened_at?->format('d/m/Y H:i') }}</b></div>
            <div class="fila">
                <span>{{ __('mus.caja.cierre') }}</span>
                <b>{{ $cash->closed_at?->format('d/m/Y H:i') ?? mb_strtolower(__('mus.caja.z_sin_cerrar')) }}</b>
            </div>
            @if ($cash->closer)
                <div class="fila"><span>{{ __('mus.caja.cerro') }}</span><b>{{ $cash->closer->name }}</b></div>
            @endif
        </div>

        <div class="sec">
            <h2>{{ __('mus.caja.ventas_turno') }}</h2>
            <div class="fila"><span>{{ __('mus.caja.z_operaciones') }}</span><b>{{ $ventas->count() }}</b></div>
            @if ($anuladas->count())
                <div class="fila"><span>{{ __('mus.ventas.anuladas') }}</span><b>{{ $anuladas->count() }}</b></div>
            @endif
            <div class="fila">
                <span>{{ __('mus.ventas.ticket_promedio') }}</span>
                <b>${{ number_format($ventas->count() ? $totalV / $ventas->count() : 0, 0, ',', '.') }}</b>
            </div>
            <div class="fila tot"><span>{{ __('mus.caja.z_total') }}</span>
                <b>${{ number_format($totalV, 0, ',', '.') }}</b></div>
        </div>

        <div class="sec">
            <h2>{{ __('mus.caja.z_cobros') }}</h2>
            @forelse ($porMedio as $m)
                <div class="fila">
                    <span>{{ $m->method_name }} <i style="color:#B8C4D4">({{ $m->veces }})</i></span>
                    <b>${{ number_format((float) $m->total, 0, ',', '.') }}</b>
                </div>
            @empty
                <div class="fila"><span>{{ __('mus.caja.z_sin_cobros') }}</span><b>—</b></div>
            @endforelse
        </div>

        <div class="sec">
            <h2>{{ __('mus.caja.z_arqueo') }}</h2>
            <div class="caja">
                <div class="fila"><span>{{ __('mus.caja.base_inicial') }}</span>
                    <b>${{ number_format((float) $cash->opening_amount, 0, ',', '.') }}</b></div>
                <div class="fila"><span>{{ __('mus.caja.z_cobros_efectivo') }}</span>
                    <b>${{ number_format($esperado - (float) $cash->opening_amount, 0, ',', '.') }}</b></div>
                <div class="fila tot"><span>{{ __('mus.caja.z_esperado') }}</span>
                    <b>${{ number_format($esperado, 0, ',', '.') }}</b></div>

                @unless ($abierta)
                    <div class="fila" style="margin-top:8px"><span>{{ __('mus.caja.contado') }}</span>
                        <b>${{ number_format((float) $cash->counted_amount, 0, ',', '.') }}</b></div>
                @endunless
            </div>

            @unless ($abierta)
                @if (abs($dif) < 0.01)
                    <div class="dif ok">{{ __('mus.caja.z_cuadro') }}</div>
                @elseif ($dif > 0)
                    <div class="dif sobra">{{ __('mus.caja.z_sobrante', ['monto' => \App\Support\Formato::moneda($dif)]) }}</div>
                @else
                    <div class="dif falta">{{ __('mus.caja.z_faltante', ['monto' => \App\Support\Formato::moneda(abs($dif))]) }}</div>
                @endif
            @endunless
        </div>

        @if ($top->count())
            <div class="sec">
                <h2>{{ __('mus.caja.z_mas_vendido') }}</h2>
                <table>
                    @foreach ($top as $t)
                        @php $unidades += (float) $t->unidades; @endphp
                        <tr>
                            <td>
                                <span class="nom">{{ \Illuminate\Support\Str::limit($t->name, 26) }}</span><br>
                                <span class="sub">{{ rtrim(rtrim(number_format((float) $t->unidades, 2, ',', '.'), '0'), ',') }} {{ __('mus.caja.z_und') }}</span>
                            </td>
                            <td class="n">${{ number_format((float) $t->total, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif

        <div class="firma">{{ __('mus.caja.z_firma_cajero') }}</div>
        <div class="firma">{{ __('mus.caja.z_firma_recibe') }}</div>

        <div class="pie">
            {{ __('mus.caja.z_generado', ['fecha' => now()->format('d/m/Y H:i')]) }}<br>
            {{ $cfg['negocio.nombre'] }}
        </div>
    </div>
</body>
</html>
