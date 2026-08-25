@php
    use App\Support\Formato;

    $nombres = [
        'tiendas'    => 'Locales',
        'usuarios'   => 'Usuarios',
        'productos'  => 'Productos',
        'ventas_mes' => 'Ventas este mes',
    ];

    $pistas = [
        'tiendas'    => 'Sedes abiertas en este negocio',
        'usuarios'   => 'Personas con cuenta',
        'productos'  => 'Referencias en el catálogo',
        'ventas_mes' => 'El contador vuelve a cero el día 1',
    ];
@endphp

<x-mus.page title="Tu plan" subtitle="Qué tienes contratado y cómo vas de cupo" icon="card">

    {{-- ══════════ Lo contratado ══════════ --}}
    <x-mus.panel :reveal="true">
        <div class="mplan__cab">
            <div>
                <span class="mplan__eti">Plan actual</span>
                <h3>{{ $plan?->nombre ?? 'Sin plan asignado' }}</h3>
                <p>{{ $plan?->descripcion ?? 'Este negocio no tiene plan puesto, así que no tiene ningún tope. Es lo normal mientras se está montando.' }}</p>
            </div>

            @if ($plan)
                <div class="mplan__precio">
                    <b>{{ Formato::moneda($aporte) }}</b>
                    <span>al mes{{ $empresa?->periodo === 'anual' ? ' (pagando el año)' : '' }}</span>
                    @if ($empresa?->precio_pactado !== null)
                        <em>precio pactado contigo</em>
                    @endif
                </div>
            @endif
        </div>

        @if ($empresa?->suscrita_desde)
            <p class="mplan__desde">
                Cliente desde {{ $empresa->suscrita_desde->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}.
            </p>
        @endif
    </x-mus.panel>

    {{-- ══════════ Cómo va el cupo ══════════ --}}
    <x-mus.panel title="Cómo vas de cupo"
                 sub="Lo que ya usaste de lo que incluye tu plan" :reveal="true">
        <div class="mcupo">
            @foreach ($panorama as $recurso => $dato)
                <div class="mcupo__i {{ $dato['apretado'] ? 'is-apretado' : '' }}">
                    <div class="mcupo__t">
                        <b>{{ $nombres[$recurso] ?? $recurso }}</b>
                        <span>
                            {{ number_format($dato['usado'], 0, ',', '.') }}
                            @if ($dato['limite'] === null)
                                <i>de ilimitado</i>
                            @else
                                <i>de {{ number_format($dato['limite'], 0, ',', '.') }}</i>
                            @endif
                        </span>
                    </div>

                    <div class="mcupo__barra" role="img"
                         aria-label="{{ $dato['porcentaje'] === null ? 'Sin límite' : $dato['porcentaje'] . ' por ciento usado' }}">
                        <span style="width:{{ $dato['porcentaje'] ?? 0 }}%"></span>
                    </div>

                    <em>{{ $pistas[$recurso] ?? '' }}</em>
                </div>
            @endforeach
        </div>
    </x-mus.panel>

    {{-- ══════════ Qué incluye cada plan ══════════ --}}
    @if ($catalogo->count())
        <x-mus.panel title="Los planes" sub="Para comparar" :pad="false" :reveal="true">
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Al mes</th>
                            <th>Al año</th>
                            <th>Locales</th>
                            <th>Usuarios</th>
                            <th>Productos</th>
                            <th>Ventas/mes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($catalogo as $p)
                            <tr data-row class="{{ $plan?->id === $p->id ? 'is-mio' : '' }}">
                                <td>
                                    <b>{{ $p->nombre }}</b>
                                    @if ($plan?->id === $p->id)
                                        <x-mus.badge tone="ok" :dot="true">El tuyo</x-mus.badge>
                                    @endif
                                </td>
                                <td>{{ Formato::moneda($p->precio_mensual) }}</td>
                                <td>
                                    {{ Formato::moneda($p->precio_anual) }}
                                    @if ($p->porcentajeAhorro() > 0)
                                        <em class="mplan__ahorro">−{{ $p->porcentajeAhorro() }}%</em>
                                    @endif
                                </td>
                                <td>{{ $p->limiteTexto('tiendas') }}</td>
                                <td>{{ $p->limiteTexto('usuarios') }}</td>
                                <td>{{ $p->limiteTexto('productos') }}</td>
                                <td>{{ $p->limiteTexto('ventas_mes') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-mus.panel>
    @endif

    {{-- ══════════ Historial ══════════ --}}
    @if ($historial->count())
        <x-mus.panel title="Movimientos" sub="Cada cambio de tu suscripción" :pad="false" :reveal="true">
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Cuándo</th>
                            <th>Qué pasó</th>
                            <th>Quedó en</th>
                            <th>Cambio</th>
                            <th>Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($historial as $m)
                            <tr data-row>
                                <td>{{ $m->ocurrio_en->locale('es')->isoFormat('D MMM YYYY') }}</td>
                                <td><b>{{ $m->etiqueta() }}</b></td>
                                <td>{{ $m->planDespues?->nombre ?? '—' }}</td>
                                <td class="{{ $m->suma() ? 'mplan__mas' : 'mplan__menos' }}">
                                    {{ $m->suma() ? '+' : '' }}{{ Formato::moneda($m->delta) }}
                                </td>
                                <td><span style="color:var(--muted-2)">{{ $m->motivo ?: '—' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-mus.panel>
    @endif

    @push('styles')
    <style>
        .mplan__cab{ display:flex; align-items:flex-start; justify-content:space-between;
                     gap:20px; flex-wrap:wrap; }
        .mplan__eti{ display:block; font-size:10.6px; font-weight:700; letter-spacing:.08em;
                     text-transform:uppercase; color:var(--muted); }
        .mplan__cab h3{ margin:5px 0 4px; font-size:21px; color:var(--ink); }
        .mplan__cab p{ margin:0; max-width:58ch; font-size:13px; line-height:1.6; color:var(--muted); }
        .mplan__precio{ flex:none; text-align:right; }
        .mplan__precio b{ display:block; font-size:23px; color:var(--ink);
                          font-variant-numeric:tabular-nums; }
        .mplan__precio span{ display:block; font-size:11.8px; color:var(--muted); }
        .mplan__precio em{ display:block; margin-top:4px; font-style:normal; font-size:10.8px;
                           color:var(--a-500); }
        .mplan__desde{ margin:14px 0 0; font-size:12.2px; color:var(--muted); }

        /* minmax(0,…) y no 1fr: con `1fr` una etiqueta larga ensancha su
           columna y las barras dejan de medir lo mismo entre ellas —que es
           justo lo único que una barra tiene que hacer bien—. */
        .mcupo{ display:grid; grid-template-columns:repeat(auto-fit, minmax(min(210px,100%), 1fr));
                gap:16px; }
        .mcupo__t{ display:flex; align-items:baseline; justify-content:space-between; gap:10px; }
        .mcupo__t b{ font-size:12.8px; color:var(--ink); }
        .mcupo__t span{ font-size:12.4px; color:var(--ink-2); font-variant-numeric:tabular-nums; }
        .mcupo__t i{ font-style:normal; color:var(--muted); }

        .mcupo__barra{ margin:8px 0 6px; height:7px; border-radius:99px;
                       background:var(--line-2); overflow:hidden; }
        .mcupo__barra span{ display:block; height:100%; border-radius:99px;
                            background:var(--a-500); transition:width .5s var(--e-soft); }
        .mcupo__i.is-apretado .mcupo__barra span{ background:var(--warn); }
        .mcupo__i em{ font-style:normal; font-size:11.2px; color:var(--muted); }

        .mplan__ahorro{ margin-left:5px; font-style:normal; font-size:10.8px; color:var(--ok); }
        .mplan__mas{ color:var(--ok); font-variant-numeric:tabular-nums; }
        .mplan__menos{ color:var(--bad); font-variant-numeric:tabular-nums; }
        .mt tr.is-mio{ background:rgba(46,110,168,.05); }
    </style>
    @endpush
</x-mus.page>
