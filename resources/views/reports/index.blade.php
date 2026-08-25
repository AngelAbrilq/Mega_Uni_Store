@php
    /* ══════════ Gráfica de barras diaria, en SVG y sin librerías ══════════

       Antes el dibujo era solo barras: no había forma de saber cuánta plata
       representaba la altura, ni de qué mes eran los días de abajo. Una
       gráfica sin ejes no es una gráfica, es un adorno.

       Ahora el lienzo reserva un margen a la izquierda para los valores del
       eje vertical y otro abajo para las fechas, y el área de las barras es
       lo que queda en el medio.                                          */
    $dias = collect($porDia);

    // Se redondea el tope hacia arriba a una cifra "bonita" (1, 2 o 5 por
    // potencia de diez) para que las marcas del eje sean leíbles: 5.000.000
    // y no 5.316.726.
    $maxDia    = (float) ($dias->max('total') ?: 0);   // el pico real, para la leyenda
    $topeCrudo = max(1, $maxDia ?: 1);
    $exp       = 10 ** max(0, floor(log10($topeCrudo)));
    $rel       = $topeCrudo / $exp;
    $topeEje   = ($rel <= 1 ? 1 : ($rel <= 2 ? 2 : ($rel <= 5 ? 5 : 10))) * $exp;

    $marcas = 4;                     // líneas horizontales sin contar la base
    $mIzq   = 62;                    // espacio para los valores del eje Y
    $mDer   = 8;
    $mArr   = 26;                    // sitio para el rótulo «PESOS», sin pisarse
    $mAba   = 42;                    // sitio para los días y el mes

    $anchoG = 900;
    $altoG  = 252;
    $trazoW = $anchoG - $mIzq - $mDer;   // ancho útil para las barras
    $trazoH = $altoG - $mArr - $mAba;    // alto útil

    $paso  = $dias->count() > 0 ? $trazoW / $dias->count() : $trazoW;
    $barra = max(3, min(26, $paso * 0.62));

    /**
     * Un número de plata en corto: 5.316.726 → «$5,3 M», 250.000 → «$250 k».
     *
     * Los ceros sobrantes solo se quitan DESPUÉS de la coma decimal. Quitarlos
     * a secas convertía «$250 k» en «$25 k», que es un error de un cero en la
     * cara del que lee el informe.
     */
    $corto = function (float $v): string {
        $limpiar = fn (string $t) => str_contains($t, ',') ? rtrim(rtrim($t, '0'), ',') : $t;

        if ($v >= 1_000_000) {
            return '$' . $limpiar(number_format($v / 1_000_000, 1, ',', '.')) . ' M';
        }
        if ($v >= 1_000) {
            return '$' . $limpiar(number_format($v / 1_000, 0, ',', '.')) . ' k';
        }
        return '$' . number_format($v, 0, ',', '.');
    };

    // El rótulo del eje horizontal viene armado del controlador.
    $mesRango = $mesRango ?? '';

    $maxCat  = max(1, collect($porCategoria)->max('ingresos') ?: 1);
    $maxProd = max(1, collect($topProductos)->max('ingresos') ?: 1);
    $totMed  = max(1, collect($porMedio)->sum('total') ?: 1);

    $margen  = $resumen['ingresos'] > 0 ? $resumen['utilidad'] / $resumen['ingresos'] * 100 : 0;

    // Cada cuántas barras se escribe la fecha debajo, para que no se amontonen.
    $saltoEje = $dias->count() <= 32 ? 1 : max(1, intdiv($dias->count(), 16));

    $promedioDia = $dias->count() > 0 ? $dias->sum('total') / $dias->count() : 0;
    $mejorDia    = $dias->sortByDesc('total')->first();
@endphp

<x-mus.page title="Reportes" subtitle="Cómo va el negocio" icon="trend">
    <x-slot name="actions">
        <x-mus.btn onclick="window.print()" icon="id">Imprimir</x-mus.btn>
    </x-slot>

    {{-- ══════════ Rango ══════════ --}}
    <form method="GET" action="{{ route('reports.index') }}" class="rrango" data-reveal>
        <div class="rrango__f">
            <label>Desde <input type="date" name="desde" value="{{ $rango['desde'] }}"></label>
            <label>Hasta <input type="date" name="hasta" value="{{ $rango['hasta'] }}"></label>
            <button type="submit" class="mb mb--primary mb--sm">Ver</button>
        </div>
        <div class="rrango__r">
            <a href="{{ route('reports.index', ['desde' => now()->format('Y-m-d'), 'hasta' => now()->format('Y-m-d')]) }}">Hoy</a>
            <a href="{{ route('reports.index', ['desde' => now()->subDays(6)->format('Y-m-d'), 'hasta' => now()->format('Y-m-d')]) }}">7 días</a>
            <a href="{{ route('reports.index', ['desde' => now()->startOfMonth()->format('Y-m-d'), 'hasta' => now()->format('Y-m-d')]) }}">Este mes</a>
            <a href="{{ route('reports.index', ['desde' => now()->subMonths(3)->format('Y-m-d'), 'hasta' => now()->format('Y-m-d')]) }}">3 meses</a>
            <a href="{{ route('reports.index', ['desde' => now()->startOfYear()->format('Y-m-d'), 'hasta' => now()->format('Y-m-d')]) }}">Este año</a>
        </div>
    </form>

    {{-- ══════════ Cifras principales ══════════ --}}
    <div class="rkpi" data-reveal data-stagger>
        <div class="rkpi__c rkpi__c--main">
            <span>Ingresos</span>
            <b><i class="moneda">$</i>{{ number_format($resumen['ingresos'], 0, ',', '.') }}</b>
            <em>{{ number_format($resumen['ventas'], 0, ',', '.') }} ventas · ticket promedio ${{ number_format($resumen['ticket'], 0, ',', '.') }}</em>
        </div>
        <div class="rkpi__c">
            <span>Utilidad bruta</span>
            <b class="ok"><i class="moneda">$</i>{{ number_format($resumen['utilidad'], 0, ',', '.') }}</b>
            <em>{{ number_format($margen, 1) }}% de margen</em>
        </div>
        <div class="rkpi__c">
            <span>Costo de lo vendido</span>
            <b><i class="moneda">$</i>{{ number_format($resumen['costo'], 0, ',', '.') }}</b>
        </div>
        <div class="rkpi__c">
            <span>Unidades vendidas</span>
            <b>{{ number_format($resumen['unidades'], 0, ',', '.') }}</b>
        </div>
        <div class="rkpi__c">
            <span>Compras recibidas</span>
            <b><i class="moneda">$</i>{{ number_format($resumen['compras'], 0, ',', '.') }}</b>
        </div>
        <div class="rkpi__c">
            <span>Ventas anuladas</span>
            <b class="{{ $resumen['anuladas'] ? 'bad' : '' }}">{{ $resumen['anuladas'] }}</b>
        </div>
    </div>

    {{-- ══════════ Ventas por día ══════════ --}}
    <x-mus.panel title="Ventas por día" sub="{{ $dias->count() }} días en el rango elegido" :reveal="true">
        @if ($dias->sum('total') > 0)
            <div class="rchart">
                {{-- Sin preserveAspectRatio="none": estirar el lienzo también
                     estiraba las letras de los ejes y se veían deformes. --}}
                <svg viewBox="0 0 {{ $anchoG }} {{ $altoG }}" role="img"
                     aria-label="Ventas por día, en pesos">
                    <defs>
                        <linearGradient id="rbar" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%"   stop-color="#4A8FC9"/>
                            <stop offset="100%" stop-color="#215480"/>
                        </linearGradient>
                    </defs>

                    {{-- ── Eje vertical: cuánta plata vale cada línea ── --}}
                    @for ($m = 0; $m <= $marcas; $m++)
                        @php
                            $y     = $mArr + $trazoH * $m / $marcas;
                            $valor = $topeEje * (1 - $m / $marcas);
                        @endphp
                        <line x1="{{ $mIzq }}" y1="{{ round($y, 2) }}"
                              x2="{{ $anchoG - $mDer }}" y2="{{ round($y, 2) }}"
                              stroke="{{ $m === $marcas ? '#C9D3E0' : '#EDF1F6' }}" stroke-width="1"/>
                        <text x="{{ $mIzq - 9 }}" y="{{ round($y + 3.5, 2) }}"
                              text-anchor="end" font-size="10.5" fill="#94A2B6"
                              font-family="Inter,system-ui,sans-serif">{{ $corto($valor) }}</text>
                    @endfor

                    {{-- ── Barras ── --}}
                    @foreach ($dias as $i => $d)
                        @php
                            $h = $d['total'] > 0 ? max(2, $d['total'] / $topeEje * $trazoH) : 0;
                            $x = $mIzq + $i * $paso + ($paso - $barra) / 2;
                        @endphp
                        @if ($h > 0)
                            <rect class="rbar" x="{{ round($x, 2) }}"
                                  y="{{ round($mArr + $trazoH - $h, 2) }}"
                                  width="{{ round($barra, 2) }}" height="{{ round($h, 2) }}"
                                  rx="{{ min(3, $barra / 3) }}" fill="url(#rbar)"
                                  style="--d:{{ $i * 22 }}ms">
                                <title>{{ $d['etiqueta'] }} — ${{ number_format($d['total'], 0, ',', '.') }} · {{ $d['ventas'] }} {{ $d['ventas'] === 1 ? 'venta' : 'ventas' }}</title>
                            </rect>
                        @endif

                        {{-- ── Eje horizontal: el día ── --}}
                        @if ($i % $saltoEje === 0)
                            {{-- Las intercaladas se esconden en celular: 31 fechas
                                 en 390 px no se leen. --}}
                            <text class="rx {{ intdiv($i, $saltoEje) % 2 ? 'rx--alt' : '' }}"
                                  x="{{ round($mIzq + $i * $paso + $paso / 2, 2) }}"
                                  y="{{ $mArr + $trazoH + 17 }}"
                                  text-anchor="middle" font-size="10.5" fill="#94A2B6"
                                  font-family="Inter,system-ui,sans-serif">{{ $d['corta'] }}</text>
                        @endif
                    @endforeach

                    {{-- ── Qué mide cada eje ── --}}
                    <text x="0" y="{{ $mArr - 12 }}" text-anchor="start"
                          font-size="9.5" fill="#A8B5C6" letter-spacing="0.1em"
                          font-family="Inter,system-ui,sans-serif">PESOS POR DÍA</text>

                    @if ($mesRango !== '')
                        <text x="{{ $anchoG - $mDer }}" y="{{ $altoG - 4 }}" text-anchor="end"
                              font-size="9.5" fill="#A8B5C6" letter-spacing="0.1em"
                              font-family="Inter,system-ui,sans-serif">
                            {{ mb_strtoupper($mesRango) }}
                        </text>
                    @endif
                </svg>
            </div>

            <div class="rleyenda">
                <span>Máximo del periodo: <b><i class="moneda">$</i>{{ number_format($maxDia, 0, ',', '.') }}</b></span>
                <span>Promedio diario: <b><i class="moneda">$</i>{{ number_format($promedioDia, 0, ',', '.') }}</b></span>
                <span>Mejor día: <b>{{ $mejorDia['etiqueta'] ?? '—' }}</b></span>
            </div>
        @else
            <x-mus.empty icon="trend" title="Sin ventas en este rango"
                         text="Elige otro periodo o registra la primera venta desde el punto de venta." />
        @endif
    </x-mus.panel>

    <div class="rgrid">
        {{-- ══════════ Productos más vendidos ══════════ --}}
        <x-mus.panel title="Productos más vendidos" :pad="false"
                     sub="Por ingresos generados" :reveal="true">
            @if (count($topProductos))
                <div class="mt-wrap">
                    <table class="mt">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="num">Unid.</th>
                                <th class="num">Ingresos</th>
                                <th class="num">Utilidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($topProductos as $p)
                                <tr data-row>
                                    <td>
                                        <b>{{ $p['nombre'] }}</b>
                                        <span class="sub">{{ $p['sku'] ?: '—' }} · {{ $p['veces'] }} ventas</span>
                                        <span class="rmini">
                                            <i style="width:{{ round($p['ingresos'] / $maxProd * 100, 1) }}%"></i>
                                        </span>
                                    </td>
                                    <td class="num">{{ rtrim(rtrim(number_format($p['unidades'], 2, ',', '.'), '0'), ',') }}</td>
                                    <td class="num"><b><i class="moneda">$</i>{{ number_format($p['ingresos'], 0, ',', '.') }}</b></td>
                                    <td class="num">
                                        <span style="color:var(--ok)"><i class="moneda">$</i>{{ number_format($p['utilidad'], 0, ',', '.') }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-mus.empty icon="box" title="Sin datos de productos" />
            @endif
        </x-mus.panel>

        <div style="display:grid;gap:16px;grid-template-columns:minmax(0,1fr);align-content:start">
            {{-- ══════════ Por categoría ══════════ --}}
            <x-mus.panel title="Por categoría" sub="Qué línea manda" :reveal="true">
                @if (count($porCategoria))
                    <ul class="rbars">
                        @foreach ($porCategoria as $i => $c)
                            <li>
                                <div class="rbars__top">
                                    <span>{{ $c['nombre'] }}</span>
                                    <b><i class="moneda">$</i>{{ number_format($c['ingresos'], 0, ',', '.') }}</b>
                                </div>
                                <div class="rbars__t">
                                    <i data-bar="{{ round($c['ingresos'] / $maxCat * 100, 1) }}"
                                       data-bar-delay="{{ 100 + $i * 70 }}" data-replay style="width:0"></i>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="rvacio">Sin ventas en el periodo.</p>
                @endif
            </x-mus.panel>

            {{-- ══════════ Medios de pago ══════════ --}}
            <x-mus.panel title="Cómo pagan" sub="Distribución del cobro" :reveal="true">
                @if (count($porMedio))
                    <ul class="rmed">
                        @foreach ($porMedio as $m)
                            @php $pct = $m['total'] / $totMed * 100; @endphp
                            <li>
                                <span>
                                    <b>{{ $m['nombre'] }}</b>
                                    <i>{{ $m['veces'] }} movimientos</i>
                                </span>
                                <span class="rmed__v">
                                    <em><i class="moneda">$</i>{{ number_format($m['total'], 0, ',', '.') }}</em>
                                    <i>{{ number_format($pct, 1) }}%</i>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="rvacio">Sin cobros en el periodo.</p>
                @endif
            </x-mus.panel>
        </div>
    </div>

    {{-- ══════════ Comparativo entre locales ══════════
         Solo aparece cuando hay más de un local: comparar una sede contra
         sí misma no responde ninguna pregunta. --}}
    @if (count($porLocal))
        <x-mus.panel title="Cómo va cada local" sub="El mismo rango, sede por sede"
                     :pad="false" :reveal="true">
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Local</th>
                            <th>Ventas</th>
                            <th>Ingresos</th>
                            <th>Del total</th>
                            <th>Utilidad</th>
                            <th>Margen</th>
                            <th>Ticket</th>
                            <th>Inventario</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($porLocal as $l)
                            <tr data-row>
                                <td>
                                    <b>{{ $l['nombre'] }}</b>
                                    @if ($l['codigo'])
                                        <kbd class="rloc__cod">{{ $l['codigo'] }}</kbd>
                                    @endif
                                </td>
                                <td>{{ number_format($l['documentos'], 0, ',', '.') }}</td>
                                <td>{{ \App\Support\Formato::moneda($l['ingresos']) }}</td>
                                <td>
                                    <span class="rloc__peso">
                                        <i style="width:{{ $l['peso'] }}%"></i>
                                        <em>{{ number_format($l['peso'], 1, ',', '.') }}%</em>
                                    </span>
                                </td>
                                <td>{{ \App\Support\Formato::moneda($l['utilidad']) }}</td>
                                <td>{{ number_format($l['margen'], 1, ',', '.') }}%</td>
                                <td>{{ \App\Support\Formato::moneda($l['ticket']) }}</td>
                                <td>
                                    {{ \App\Support\Formato::moneda($l['inventario']) }}
                                    <em class="rloc__und">{{ rtrim(rtrim(number_format($l['unidades'], 3, ',', '.'), '0'), ',') }} und</em>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="rloc__nota">
                Una sede puede no haber vendido nada en este rango y aun así tener plata parada
                en bodega. Por eso el inventario se cuenta aparte de las ventas: si saliera de la
                misma consulta, esa sede desaparecería del informe justo cuando es la que hay que mirar.
            </p>
        </x-mus.panel>

        @push('styles')
        <style>
            .rloc__cod{ margin-left:6px; padding:1px 5px; border-radius:4px; font-size:9.8px;
                        font-family:inherit; background:var(--line-2); color:var(--muted); }
            /* La barra y el número van juntos: el número solo no deja
               comparar de un vistazo, y la barra sola no deja leer el dato. */
            .rloc__peso{ display:flex; align-items:center; gap:8px; min-width:0; }
            .rloc__peso i{ display:block; height:6px; min-width:2px; max-width:70px; flex:none;
                           border-radius:99px; background:var(--a-500); }
            .rloc__peso em{ font-style:normal; font-size:11.6px; color:var(--muted);
                            font-variant-numeric:tabular-nums; }
            .rloc__und{ display:block; font-style:normal; font-size:11px; color:var(--muted); }
            .rloc__nota{ margin:0; padding:12px 15px; border-top:1px solid var(--line-2);
                         max-width:78ch; font-size:11.8px; line-height:1.6; color:var(--muted); }
        </style>
        @endpush
    @endif

    <div class="rgrid">
        {{-- ══════════ Vendedores ══════════ --}}
        <x-mus.panel title="Desempeño por vendedor" :pad="false" :reveal="true">
            @if (count($porVendedor))
                <div class="mt-wrap">
                    <table class="mt">
                        <thead>
                            <tr>
                                <th>Vendedor</th>
                                <th class="num">Ventas</th>
                                <th class="num">Facturado</th>
                                <th class="num">Utilidad</th>
                                <th class="num">Ticket</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($porVendedor as $v)
                                <tr data-row>
                                    <td><b>{{ $v['nombre'] }}</b></td>
                                    <td class="num">{{ $v['ventas'] }}</td>
                                    <td class="num"><b><i class="moneda">$</i>{{ number_format($v['total'], 0, ',', '.') }}</b></td>
                                    <td class="num"><span style="color:var(--ok)"><i class="moneda">$</i>{{ number_format($v['utilidad'], 0, ',', '.') }}</span></td>
                                    <td class="num"><i class="moneda">$</i>{{ number_format($v['ventas'] ? $v['total'] / $v['ventas'] : 0, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-mus.empty icon="users" title="Sin ventas en el periodo" />
            @endif
        </x-mus.panel>

        {{-- ══════════ Inventario ══════════ --}}
        <x-mus.panel title="Estado del inventario" sub="Foto de hoy, no del periodo" :reveal="true">
            <div class="rinv">
                <div><span>Valor al costo</span><b><i class="moneda">$</i>{{ number_format($inventario['valor'], 0, ',', '.') }}</b></div>
                <div><span>Valor a precio de venta</span><b><i class="moneda">$</i>{{ number_format($inventario['venta'], 0, ',', '.') }}</b></div>
                <div><span>Utilidad potencial</span>
                    <b class="ok"><i class="moneda">$</i>{{ number_format($inventario['venta'] - $inventario['valor'], 0, ',', '.') }}</b></div>
                <div><span>Unidades</span><b>{{ number_format($inventario['unidades'], 0, ',', '.') }}</b></div>
                <div><span>Por reponer</span>
                    <b class="{{ $inventario['bajos'] ? 'warn' : '' }}">{{ $inventario['bajos'] }}</b></div>
            </div>

            @if (count($sinRotacion))
                <h4 class="rsub">Plata quieta: no se vendió en el periodo</h4>
                <ul class="rquieto">
                    @foreach ($sinRotacion as $p)
                        <li>
                            <span>
                                <b>{{ $p['nombre'] }}</b>
                                <i>{{ rtrim(rtrim(number_format($p['stock'], 2, ',', '.'), '0'), ',') }} unidades en bodega</i>
                            </span>
                            <em><i class="moneda">$</i>{{ number_format($p['inmovil'], 0, ',', '.') }}</em>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-mus.panel>
    </div>

    @push('styles')
    <style>
        .rrango{ display:flex; align-items:center; justify-content:space-between; gap:14px;
                 flex-wrap:wrap; margin-bottom:16px; padding:13px 16px;
                 background:var(--card); border:1px solid var(--line); border-radius:12px; }
        .rrango__f{ display:flex; align-items:center; gap:9px; flex-wrap:wrap; }
        .rrango__f label{ display:flex; align-items:center; gap:6px; font-size:12.2px; color:var(--muted); }
        .rrango__f input{ height:36px; padding:0 9px; border:1px solid var(--line); border-radius:9px;
                          background:#fff; font:inherit; font-size:12.6px; color:var(--ink); }
        .rrango__r{ display:flex; gap:6px; flex-wrap:wrap; }
        .rrango__r a{ padding:6px 11px; border-radius:8px; font-size:12.2px; font-weight:600;
                      text-decoration:none; color:var(--ink-2); background:var(--paper);
                      border:1px solid var(--line); transition:all .16s var(--e-soft); }
        .rrango__r a:hover{ border-color:var(--a-400); color:var(--a-600); }

        .rkpi{ display:grid; gap:12px; margin-bottom:16px;
               grid-template-columns:repeat(auto-fit,minmax(178px,1fr)); }
        .rkpi__c{ padding:15px 17px; background:var(--card); border:1px solid var(--line);
                  border-radius:12px; }
        .rkpi__c--main{ grid-column:span 2; background:linear-gradient(150deg,var(--n-850),var(--n-900));
                        border-color:var(--n-700); }
        .rkpi__c--main > span{ color:var(--a-300) !important; }
        .rkpi__c--main b{ color:#fff !important; font-size:30px !important; }
        .rkpi__c--main em{ color:#8FA3BC !important; }
        @media (max-width:640px){ .rkpi__c--main{ grid-column:span 1; } }
        .rkpi__c > span{ display:block; font-size:11.1px; letter-spacing:.05em; text-transform:uppercase;
                         color:var(--muted-2); font-weight:600; }
        .rkpi__c b{ display:block; margin:6px 0 2px; font-size:23px; font-weight:700;
                    letter-spacing:-.03em; color:var(--ink); line-height:1.05;
                    font-variant-numeric:tabular-nums; }
        .rkpi__c b.ok{ color:var(--ok); }
        .rkpi__c b.bad{ color:var(--bad); }
        .rkpi__c b.warn{ color:var(--warn); }
        .rkpi__c em{ display:block; font-style:normal; font-size:11.5px; color:var(--muted); }

        .rchart{ margin:4px 0 2px; }
        .rchart svg{ width:100%; height:auto; overflow:visible; }

        /* En celular la gráfica no se encoge hasta volverse ilegible: se le
           fija el alto y se desplaza de lado, que es como se leen las
           gráficas en un teléfono. */
        @media (max-width:760px){
            .rchart{ overflow-x:auto; overflow-y:hidden; padding-bottom:4px;
                     scrollbar-width:thin; }
            .rchart svg{ width:auto; height:230px; min-width:660px; }
            .rchart text.rx--alt{ display:none; }
        }
        .rbar{ transform-box:fill-box; transform-origin:bottom; transform:scaleY(0);
               animation:rgrow .8s var(--e-soft) forwards; animation-delay:var(--d); }
        @keyframes rgrow{ to{ transform:scaleY(1); } }
        .rbar:hover{ filter:brightness(1.12); }

        .rleyenda{ display:flex; gap:20px; flex-wrap:wrap; margin-top:10px; padding-top:11px;
                   border-top:1px solid var(--line-2); font-size:12.2px; color:var(--muted); }
        .rleyenda b{ color:var(--ink); font-variant-numeric:tabular-nums; }

        .rgrid{ display:grid; gap:16px; margin-top:16px;
                grid-template-columns:minmax(0,1.5fr) minmax(0,1fr); align-items:start; }
        @media (max-width:1000px){ .rgrid{ grid-template-columns:minmax(0,1fr); } }

        .rmini{ display:block; height:3px; margin-top:5px; border-radius:99px; background:var(--line-2); }
        .rmini i{ display:block; height:100%; border-radius:99px;
                  background:linear-gradient(90deg,var(--a-400),var(--a-600)); }

        .rbars{ list-style:none; margin:0; padding:0; display:grid; gap:12px; }
        .rbars__top{ display:flex; justify-content:space-between; gap:10px; margin-bottom:5px;
                     font-size:12.6px; color:var(--ink-2); }
        .rbars__top b{ font-weight:700; color:var(--ink); font-variant-numeric:tabular-nums; }
        .rbars__t{ height:7px; border-radius:99px; background:var(--line-2); overflow:hidden; }
        .rbars__t i{ display:block; height:100%; border-radius:99px;
                     background:linear-gradient(90deg,var(--a-400),var(--a-600));
                     transition:width 1s var(--e-soft); }

        .rmed{ list-style:none; margin:0; padding:0; }
        .rmed li{ display:flex; align-items:center; justify-content:space-between; gap:12px;
                  padding:10px 0; border-bottom:1px solid var(--line-2); }
        .rmed li:last-child{ border-bottom:0; }
        .rmed b{ display:block; font-size:12.9px; font-weight:600; color:var(--ink); }
        .rmed i{ display:block; font-style:normal; font-size:11.2px; color:var(--muted); }
        .rmed__v{ text-align:right; }
        .rmed__v em{ display:block; font-style:normal; font-size:13.6px; font-weight:700;
                     color:var(--ink); font-variant-numeric:tabular-nums; }
        .rmed__v i{ color:var(--a-600); font-weight:600; }

        .rinv > div{ display:flex; justify-content:space-between; align-items:baseline; gap:12px;
                     padding:8px 0; border-bottom:1px solid var(--line-2); font-size:12.7px;
                     color:var(--muted); }
        .rinv > div:last-child{ border-bottom:0; }
        .rinv b{ font-size:14.5px; font-weight:700; color:var(--ink);
                 font-variant-numeric:tabular-nums; }
        .rinv b.ok{ color:var(--ok); }
        .rinv b.warn{ color:var(--warn); }

        .rsub{ margin:16px 0 8px; padding-top:13px; border-top:1px solid var(--line-2);
               font-size:12.2px; font-weight:600; letter-spacing:.04em; text-transform:uppercase;
               color:var(--muted-2); }
        .rquieto{ list-style:none; margin:0; padding:0; }
        .rquieto li{ display:flex; align-items:center; justify-content:space-between; gap:12px;
                     padding:8px 0; border-bottom:1px solid var(--line-2); }
        .rquieto li:last-child{ border-bottom:0; }
        .rquieto b{ display:block; font-size:12.6px; font-weight:600; color:var(--ink); }
        .rquieto i{ display:block; font-style:normal; font-size:11.2px; color:var(--muted); }
        .rquieto em{ font-style:normal; font-size:13px; font-weight:700; color:var(--warn);
                     font-variant-numeric:tabular-nums; white-space:nowrap; }

        .rvacio{ margin:0; font-size:12.8px; color:var(--muted); }

        @media print{
            .mus-side, .mus-top, .rrango__r, .mh__r{ display:none !important; }
            .mus-main{ padding-left:0 !important; }
            .rgrid{ grid-template-columns:1fr 1fr; }
        }

        /* ── Celular: rango de fechas apilado ── */
        @media (max-width:760px){
            .rrango{ flex-direction:column; align-items:stretch; gap:11px; }
            .rrango__f{ flex-direction:column; align-items:stretch; }
            .rrango__f label{ justify-content:space-between; }
            .rrango__f input{ flex:1; min-width:0; height:42px; font-size:16px; }
            .rrango__r{ justify-content:center; }
        }
    </style>
    @endpush
</x-mus.page>
