@php
    use Illuminate\Support\Str;

    /* ══════════════ Paleta por módulo ══════════════ */
    $tonos = [
        'blue'    => ['#2E6EA8', '#5E97C8', '#EDF3F9'],
        'sky'     => ['#3E6C8C', '#6E9AB5', '#EEF3F7'],
        'emerald' => ['#3E7D5C', '#6BA487', '#EDF4F0'],
        'amber'   => ['#96703C', '#BC9A6A', '#F7F3EB'],
        'violet'  => ['#5E5F94', '#8B8CBA', '#F0F0F6'],
        'rose'    => ['#96504F', '#BC7C7B', '#F8EFEF'],
        'cyan'    => ['#2F7E7A', '#5FA6A2', '#EDF5F4'],
        'slate'   => ['#5A6779', '#8B96A6', '#F0F2F5'],
    ];

    $iconos = [
        'box'    => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
        'users'  => '<circle cx="9" cy="8" r="3.4"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M17 8.2a3 3 0 0 1 0 5.6M18 20a5.6 5.6 0 0 0-2-4.3"/>',
        'truck'  => '<path d="M2 7h11v10H2z"/><path d="M13 10h4l4 3.5V17h-8z"/><circle cx="6.5" cy="18.5" r="1.8"/><circle cx="17" cy="18.5" r="1.8"/>',
        'ruler'  => '<rect x="2.5" y="8.5" width="19" height="7" rx="1.6"/><path d="M7 8.5v3M11 8.5v4.5M15 8.5v3M19 8.5v4.5"/>',
        'percent'=> '<path d="M19 5 5 19"/><circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/>',
        'card'   => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6 15h4"/>',
        'tag'    => '<path d="M20.5 12.5 12 21l-9-9V3h9z"/><circle cx="7.5" cy="7.5" r="1.6"/>',
    ];

    /* ══════════════ Generador de curvas suaves ══════════════ */
    $curva = function (array $vals, float $w, float $h, float $pt, float $pb, ?float $max = null, float $px = 0) {
        $n = count($vals);
        if ($n === 0) {
            return ['linea' => '', 'area' => '', 'puntos' => []];
        }
        $max  = $max ?? max(1, max($vals));
        $max  = $max <= 0 ? 1 : $max;
        $base = $h - $pb;
        $alto = $base - $pt;
        $paso = $n > 1 ? ($w - 2 * $px) / ($n - 1) : 0;

        $pts = [];
        foreach ($vals as $i => $v) {
            $pts[] = [
                round($px + $i * $paso, 2),
                round($base - ($v / $max) * $alto, 2),
            ];
        }

        $d = 'M' . $pts[0][0] . ',' . $pts[0][1];
        for ($i = 1; $i < $n; $i++) {
            $c = $paso * 0.4;
            $d .= ' C' . round($pts[$i - 1][0] + $c, 2) . ',' . $pts[$i - 1][1]
                . ' ' . round($pts[$i][0] - $c, 2) . ',' . $pts[$i][1]
                . ' ' . $pts[$i][0] . ',' . $pts[$i][1];
        }

        /* El relleno se estira hasta los bordes del lienzo para que no quede
           un corte vertical seco donde termina la línea. */
        $area = $d
            . ' L' . round($w, 2) . ',' . $pts[$n - 1][1]
            . ' L' . round($w, 2) . ',' . $base
            . ' L0,' . $base
            . ' L0,' . $pts[0][1]
            . ' Z';

        return ['linea' => $d, 'area' => $area, 'puntos' => $pts];
    };

    /* ══════════════ Totales ══════════════ */
    $totalRegistros = collect($modules)->sum(fn ($m) => $m['count'] ?? 0);
    $modulosListos  = collect($modules)->filter(fn ($m) => $m['count'] !== null)->count();
    $nProductos     = collect($modules)->firstWhere('key', 'products')['count'] ?? 0;
    $nClientes      = collect($modules)->firstWhere('key', 'customers')['count'] ?? 0;

    $usuario  = auth()->user();
    $primer   = Str::of($usuario->name)->trim()->explode(' ')->first();
    $roles    = method_exists($usuario, 'getRoleNames') ? $usuario->getRoleNames() : collect();
    $hora     = (int) now()->format('H');
    $saludo   = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');

    /* ══════════════ Gráfica mensual ══════════════ */
    $vProd   = array_column($serieMeses, 'productos');
    $vCli    = array_column($serieMeses, 'clientes');
    $maxMes  = max(1, max(array_merge($vProd, $vCli)));
    $hayDatos= array_sum($vProd) + array_sum($vCli) > 0;

    $cProd = $curva($vProd, 620, 210, 16, 30, $maxMes, 14);
    $cCli  = $curva($vCli,  620, 210, 16, 30, $maxMes, 14);

    $maxCat = collect($topCategorias)->max('total') ?: 1;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="d-head">
            <div class="d-head__txt">
                <h1>Panel de control</h1>
                <p>{{ $saludo }}, {{ $primer }} · esto es lo que hay en tu tienda hoy</p>
            </div>
            <div class="d-head__chips">
                @if ($roles->isNotEmpty())
                    @foreach ($roles as $rol)
                        <span class="d-chip d-chip--blue">{{ $rol }}</span>
                    @endforeach
                @else
                    <span class="d-chip">Sin rol asignado</span>
                @endif
                <span class="d-chip d-chip--soft">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3.2" y="4.8" width="17.6" height="16" rx="2.6"/>
                        <path d="M3.2 9.6h17.6M8 3.2v3.2M16 3.2v3.2"/>
                    </svg>
                    {{ now()->locale('es')->isoFormat('D [de] MMMM, YYYY') }}
                </span>
            </div>
        </div>
    </x-slot>

    {{-- ══════════════════════════════════════════════════════════
         BANDA SUPERIOR
         ══════════════════════════════════════════════════════════ --}}
    <section class="d-tira" data-reveal>
        <div class="d-tira__enc">
            <div class="d-tira__t">
                <h2>Resumen</h2>
                <p>Lo que hay en el sistema hoy, {{ now()->locale('es')->isoFormat('D [de] MMMM') }}.</p>
            </div>

            <div class="d-tira__cta">
                @if (Route::has('products.index'))
                    <a href="{{ route('products.index') }}" class="d-b d-b--g">Ver catálogo</a>
                @endif
                @if (Route::has('products.create'))
                    <a href="{{ route('products.create') }}" class="d-b"
                       data-modal="Nuevo producto" data-modal-ancho="820">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                            <path d="M12 5v14M5 12h14"/>
                        </svg>
                        Nuevo producto
                    </a>
                @endif
            </div>
        </div>

        {{-- Las cuatro cifras van pegadas en una sola pieza: se leen como
             una tabla, no como cuatro tarjetas sueltas. --}}
        <div class="d-tira__cifras">
            <div class="d-tira__c">
                <span>Registros totales</span>
                <b class="cifra" data-count="{{ $totalRegistros }}" data-replay>0</b>
            </div>
            <div class="d-tira__c">
                <span>Productos</span>
                <b class="cifra" data-count="{{ $nProductos }}" data-replay>0</b>
            </div>
            <div class="d-tira__c">
                <span>Clientes</span>
                <b class="cifra" data-count="{{ $nClientes }}" data-replay>0</b>
            </div>
            <div class="d-tira__c">
                <span>Módulos activos</span>
                <b class="cifra" data-count="{{ $modulosListos }}" data-replay>0</b>
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════
         TARJETAS KPI CON MINI-GRÁFICA
         ══════════════════════════════════════════════════════════ --}}
    <div class="d-sec-title" data-reveal>
        <h3>Módulos</h3>
        <span></span>
    </div>

    <div class="d-grid" data-stagger="70">
        @foreach ($modules as $m)
            @php
                [$c1, $c2, $soft] = $tonos[$m['tone']] ?? $tonos['slate'];
                $disp  = Route::has($m['route']);
                $spark = $curva($m['spark'], 132, 46, 6, 6);
                $uid   = 'sp' . $m['key'];
                $delta = ($m['spark'][7] ?? 0) - ($m['spark'][6] ?? 0);
            @endphp

            <a href="{{ $disp ? route($m['route']) : '#' }}"
               class="d-card {{ $disp ? '' : 'is-off' }}"
               data-reveal data-replay
               style="--c1:{{ $c1 }};--c2:{{ $c2 }};--soft:{{ $soft }}">

                <span class="d-card__top"></span>

                <div class="d-card__row">
                    <span class="d-card__ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                             stroke-linecap="round" stroke-linejoin="round">
                            {!! $iconos[$m['icon']] ?? $iconos['tag'] !!}
                        </svg>
                    </span>

                    @if ($m['count'] !== null && $delta != 0)
                        <span class="d-card__delta {{ $delta > 0 ? 'up' : 'down' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <path d="{{ $delta > 0 ? 'M12 19V5M6 11l6-6 6 6' : 'M12 5v14M6 13l6 6 6-6' }}"/>
                            </svg>
                            {{ abs($delta) }}
                        </span>
                    @else
                        <span class="d-card__arrow">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14M13 6l6 6-6 6"/>
                            </svg>
                        </span>
                    @endif
                </div>

                <div class="d-card__num">
                    @if ($m['count'] === null)
                        <em>—</em>
                    @else
                        <b data-count="{{ $m['count'] }}" data-count-dur="1400" data-replay>0</b>
                    @endif
                </div>
                <div class="d-card__lbl">{{ $m['label'] }}</div>
                <div class="d-card__hint">{{ $m['count'] === null ? 'Tabla sin migrar' : $m['hint'] }}</div>

                <svg class="d-card__spark" viewBox="0 0 132 46" preserveAspectRatio="none" aria-hidden="true">
                    <defs>
                        <linearGradient id="{{ $uid }}" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%"   stop-color="{{ $c2 }}" stop-opacity=".38"/>
                            <stop offset="100%" stop-color="{{ $c2 }}" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <path d="{{ $spark['area'] }}" fill="url(#{{ $uid }})"
                          data-area data-area-opacity="1" data-area-delay="420" style="opacity:0"/>
                    <path d="{{ $spark['linea'] }}" fill="none" stroke="{{ $c1 }}"
                          stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                          data-draw data-draw-dur="1300" data-replay/>
                </svg>
            </a>
        @endforeach
    </div>

    {{-- ══════════════════════════════════════════════════════════
         GRÁFICAS
         ══════════════════════════════════════════════════════════ --}}
    <div class="d-sec-title" data-reveal>
        <h3>Actividad</h3>
        <span></span>
    </div>

    <div class="d-charts">

        {{-- ---------- Área: registros por mes ---------- --}}
        <section class="d-panel d-panel--chart" data-reveal="left" data-replay>
            <header class="d-panel__head">
                <div>
                    <h4>Registros por mes</h4>
                    <p>Últimos 6 meses</p>
                </div>
                <div class="d-legend">
                    <span><i style="background:#2E6EA8"></i> Productos</span>
                    <span><i style="background:#7FB2DE"></i> Clientes</span>
                </div>
            </header>

            <div class="d-chart" id="dChart"
                 data-serie='@json(collect($serieMeses)->map(fn ($m) => ["e" => $m["etiqueta"], "p" => $m["productos"], "c" => $m["clientes"]])->all())'
                 data-pts='@json(collect($cProd["puntos"])->map(fn ($p) => $p[0] / 620 * 100)->all())'>
                <span class="mchart-cross" id="dCross"></span>
                <div class="mchart-tip" id="dTip"></div>

                {{-- Eje vertical. Va en HTML y no dentro del SVG porque el
                     lienzo se estira a lo ancho («preserveAspectRatio=none»)
                     y cualquier texto adentro saldría deformado. --}}
                <div class="d-chart__y" aria-hidden="true">
                    @for ($g = 0; $g <= 3; $g++)
                        <span style="top:{{ (16 + $g * ((210 - 30 - 16) / 3)) / 210 * 100 }}%">
                            {{ number_format($maxMes * (1 - $g / 3), 0, ',', '.') }}
                        </span>
                    @endfor
                </div>
                <svg viewBox="0 0 620 210" preserveAspectRatio="none" class="d-chart__svg">
                    <defs>
                        <linearGradient id="gProd" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%"   stop-color="#2E6EA8" stop-opacity=".30"/>
                            <stop offset="100%" stop-color="#2E6EA8" stop-opacity="0"/>
                        </linearGradient>
                        <linearGradient id="gCli" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%"   stop-color="#7FB2DE" stop-opacity=".26"/>
                            <stop offset="100%" stop-color="#7FB2DE" stop-opacity="0"/>
                        </linearGradient>
                    </defs>

                    {{-- rejilla --}}
                    @for ($g = 0; $g <= 3; $g++)
                        <line x1="0" x2="620" y1="{{ 16 + $g * ((210 - 30 - 16) / 3) }}"
                              y2="{{ 16 + $g * ((210 - 30 - 16) / 3) }}"
                              stroke="#DFE5EC" stroke-width="1" stroke-dasharray="3 5"/>
                    @endfor

                    <path d="{{ $cProd['area'] }}" fill="url(#gProd)"
                          data-area data-area-delay="500" style="opacity:0"/>
                    <path d="{{ $cCli['area'] }}" fill="url(#gCli)"
                          data-area data-area-delay="700" style="opacity:0"/>

                    <path d="{{ $cCli['linea'] }}" fill="none" stroke="#7FB2DE" stroke-width="2.6"
                          stroke-linecap="round" stroke-linejoin="round"
                          data-draw data-draw-dur="1900" data-draw-delay="220" data-replay/>
                    <path d="{{ $cProd['linea'] }}" fill="none" stroke="#2E6EA8" stroke-width="3"
                          stroke-linecap="round" stroke-linejoin="round"
                          data-draw data-draw-dur="1900" data-replay/>
                </svg>

                {{-- puntos y valores, en capa HTML para que no se deformen --}}
                <div class="d-chart__dots">
                    @foreach ($cProd['puntos'] as $i => $p)
                        <span class="d-dot" style="left:{{ $p[0] / 620 * 100 }}%;top:{{ $p[1] / 210 * 100 }}%;--dl:{{ 700 + $i * 90 }}ms"></span>
                    @endforeach
                </div>

                <div class="d-chart__x">
                    @foreach ($serieMeses as $mes)
                        <span>{{ $mes['etiqueta'] }}</span>
                    @endforeach
                </div>

                @unless ($hayDatos)
                    <div class="d-chart__empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>
                        </svg>
                        Aún no hay movimientos registrados
                    </div>
                @endunless
            </div>
        </section>

        {{-- ---------- Barras: top categorías ---------- --}}
        <section class="d-panel" data-reveal="right" data-replay>
            <header class="d-panel__head">
                <div>
                    <h4>Productos por categoría</h4>
                    <p>Las seis con más artículos</p>
                </div>
            </header>

            @if (count($topCategorias))
                <ul class="d-bars" data-stagger="90">
                    @foreach ($topCategorias as $i => $cat)
                        <li data-reveal data-replay>
                            <div class="d-bars__top">
                                <span class="d-bars__name">{{ $cat['nombre'] }}</span>
                                <span class="d-bars__val" data-count="{{ $cat['total'] }}" data-count-dur="1200" data-replay>0</span>
                            </div>
                            <div class="d-bars__track">
                                <i data-bar="{{ $maxCat > 0 ? round($cat['total'] / $maxCat * 100, 1) : 0 }}"
                                   data-bar-delay="{{ 120 + $i * 90 }}"
                                   data-replay
                                   style="width:0"></i>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="d-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>
                    </svg>
                    <p>Todavía no hay categorías con productos.</p>
                    @if (Route::has('categories.create'))
                        <a href="{{ route('categories.create') }}">Crear la primera</a>
                    @endif
                </div>
            @endif
        </section>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         VENTAS
         ══════════════════════════════════════════════════════════ --}}
    <div class="d-sec-title" data-reveal>
        <h3>Ventas</h3>
        <span></span>
    </div>

    <div class="d-ven">
        <section class="d-panel" data-reveal="left" data-replay>
            <header class="d-panel__head">
                <div>
                    <h4>Cómo va la caja</h4>
                    <p>
                        @if ($ventas['turno'])
                            Turno abierto desde las {{ $ventas['turno']->opened_at?->format('H:i') }}
                        @else
                            Sin turno de caja abierto
                        @endif
                    </p>
                </div>
                @if (Route::has('pos.index'))
                    <a href="{{ route('pos.index') }}" class="d-more">
                        Vender
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                             stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                @endif
            </header>

            <div class="d-ven__cifras">
                <div class="d-ven__c d-ven__c--hoy">
                    <span>Hoy</span>
                    <b><i class="moneda">$</i>{{ number_format($ventas['hoy'], 0, ',', '.') }}</b>
                    <em>{{ $ventas['hoy_n'] }} {{ $ventas['hoy_n'] === 1 ? 'venta' : 'ventas' }}</em>
                </div>
                <div class="d-ven__c">
                    <span>Esta semana</span>
                    <b><i class="moneda">$</i>{{ number_format($ventas['semana'], 0, ',', '.') }}</b>
                </div>
                <div class="d-ven__c">
                    <span>Este mes</span>
                    <b><i class="moneda">$</i>{{ number_format($ventas['mes'], 0, ',', '.') }}</b>
                    <em>{{ $ventas['mes_n'] }} ventas</em>
                </div>
                <div class="d-ven__c">
                    <span>Utilidad del mes</span>
                    <b class="ok"><i class="moneda">$</i>{{ number_format($ventas['utilidad_mes'], 0, ',', '.') }}</b>
                    <em>ticket ${{ number_format($ventas['ticket'], 0, ',', '.') }}</em>
                </div>
            </div>
        </section>

        <section class="d-panel" data-reveal="right" data-replay>
            <header class="d-panel__head">
                <div>
                    <h4>Últimas ventas</h4>
                    <p>Lo más reciente de la caja</p>
                </div>
                @if (Route::has('sales.index'))
                    <a href="{{ route('sales.index') }}" class="d-more">
                        Ver todas
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                             stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                @endif
            </header>

            @if (count($ventas['ultimas']))
                <ul class="d-list" data-stagger="80">
                    @foreach ($ventas['ultimas'] as $v)
                        <li data-reveal data-replay>
                            <span class="d-list__av" style="--c1:#215480;--c2:#4A8FC9">
                                {{ Str::substr($v['numero'], -2) }}
                            </span>
                            <span class="d-list__main">
                                <b>{{ $v['cliente'] }}</b>
                                <em>{{ $v['numero'] }} · {{ $v['vendedor'] }} · {{ $v['cuando'] }}</em>
                            </span>
                            <span class="d-list__side">
                                <b><i class="moneda">$</i>{{ number_format($v['total'], 0, ',', '.') }}</b>
                                <em class="{{ $v['anulada'] ? 'off' : 'ok' }}">
                                    {{ $v['anulada'] ? 'Anulada' : 'Pagada' }}
                                </em>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="d-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                         stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2.5" y="6" width="19" height="12" rx="2.5"/><circle cx="12" cy="12" r="2.6"/>
                    </svg>
                    <p>Todavía no se ha registrado ninguna venta.</p>
                    @if (Route::has('pos.index'))
                        <a href="{{ route('pos.index') }}">Ir al punto de venta</a>
                    @endif
                </div>
            @endif
        </section>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         INVENTARIO
         ══════════════════════════════════════════════════════════ --}}
    <div class="d-sec-title" data-reveal>
        <h3>Inventario</h3>
        <span></span>
    </div>

    <div class="d-inv">

        {{-- ---------- Dinero en bodega ---------- --}}
        <section class="d-panel" data-reveal="left" data-replay>
            <header class="d-panel__head">
                <div>
                    <h4>Dinero en bodega</h4>
                    <p>Existencias valorizadas</p>
                </div>
            </header>

            <div class="d-inv__cifras">
                <div class="d-inv__c">
                    <span>Al costo</span>
                    <b><i class="moneda">$</i>{{ number_format($inventario['valor_costo'], 0, ',', '.') }}</b>
                    <em>lo que ya pagaste</em>
                </div>
                <div class="d-inv__c">
                    <span>A precio de venta</span>
                    <b><i class="moneda">$</i>{{ number_format($inventario['valor_venta'], 0, ',', '.') }}</b>
                    <em>si se vendiera todo</em>
                </div>
                <div class="d-inv__c">
                    <span>Utilidad potencial</span>
                    <b class="ok"><i class="moneda">$</i>{{ number_format($inventario['valor_venta'] - $inventario['valor_costo'], 0, ',', '.') }}</b>
                    <em>{{ $inventario['valor_venta'] > 0
                            ? number_format(($inventario['valor_venta'] - $inventario['valor_costo']) / $inventario['valor_venta'] * 100, 1)
                            : '0' }}% de margen global</em>
                </div>
                <div class="d-inv__c">
                    <span>Unidades en piso</span>
                    <b data-count="{{ $inventario['unidades'] }}" data-count-dur="1400" data-replay>0</b>
                    <em>sumando todas las referencias</em>
                </div>
            </div>
        </section>

        {{-- ---------- Reposición ---------- --}}
        <section class="d-panel" data-reveal="right" data-replay>
            <header class="d-panel__head">
                <div>
                    <h4>Hay que reponer</h4>
                    <p>{{ $inventario['bajos'] }} por debajo del mínimo · {{ $inventario['agotados'] }} agotados</p>
                </div>
                @if (Route::has('products.index'))
                    <a href="{{ route('products.index', ['bajo' => 1]) }}" class="d-more">
                        Ver todos
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                             stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                @endif
            </header>

            @if (count($inventario['lista']))
                <ul class="d-rep" data-stagger="80">
                    @foreach ($inventario['lista'] as $i => $p)
                        @php
                            $meta  = max(1, $p['minimo']);
                            $pct   = min(100, round($p['stock'] / $meta * 100, 1));
                        @endphp
                        <li data-reveal data-replay>
                            <div class="d-rep__top">
                                <span class="d-rep__name">
                                    @if (Route::has('products.show'))
                                        <a href="{{ route('products.show', $p['id']) }}">{{ $p['nombre'] }}</a>
                                    @else
                                        {{ $p['nombre'] }}
                                    @endif
                                    <em>{{ $p['categoria'] }}</em>
                                </span>
                                <span class="d-rep__val {{ $p['agotado'] ? 'bad' : 'warn' }}">
                                    {{ $p['stock'] }} / {{ $p['minimo'] }} {{ $p['simbolo'] }}
                                </span>
                            </div>
                            <div class="d-rep__track">
                                <i class="{{ $p['agotado'] ? 'bad' : 'warn' }}"
                                   data-bar="{{ $pct }}" data-bar-delay="{{ 120 + $i * 80 }}"
                                   data-replay style="width:0"></i>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="d-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5"/>
                    </svg>
                    <p>Ningún producto está por debajo de su mínimo.</p>
                </div>
            @endif
        </section>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         ÚLTIMOS REGISTROS
         ══════════════════════════════════════════════════════════ --}}
    <div class="d-sec-title" data-reveal>
        <h3>Lo más reciente</h3>
        <span></span>
    </div>

    <div class="d-charts">
        {{-- ---------- Productos ---------- --}}
        <section class="d-panel" data-reveal="left" data-replay>
            <header class="d-panel__head">
                <div>
                    <h4>Últimos productos</h4>
                    <p>Los cinco más nuevos del catálogo</p>
                </div>
                @if (Route::has('products.index'))
                    <a href="{{ route('products.index') }}" class="d-more">
                        Ver todos
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                             stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                @endif
            </header>

            @if (count($ultProductos))
                <ul class="d-list" data-stagger="80">
                    @foreach ($ultProductos as $p)
                        <li data-reveal data-replay>
                            <span class="d-list__av" style="--c1:#2E6EA8;--c2:#7FB2DE">
                                {{ Str::upper(Str::substr($p['nombre'], 0, 1)) }}
                            </span>
                            <span class="d-list__main">
                                <b>{{ $p['nombre'] }}</b>
                                <em>{{ $p['categoria'] }} · {{ $p['fecha'] }}</em>
                            </span>
                            <span class="d-list__side">
                                <b><i class="moneda">$</i>{{ number_format($p['precio'], 0, ',', '.') }}</b>
                                <em class="{{ $p['activo'] ? 'ok' : 'off' }}">
                                    {{ $p['activo'] ? 'Activo' : 'Inactivo' }}
                                </em>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="d-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/>
                    </svg>
                    <p>El catálogo está vacío por ahora.</p>
                    @if (Route::has('products.create'))
                        <a href="{{ route('products.create') }}">Agregar el primero</a>
                    @endif
                </div>
            @endif
        </section>

        {{-- ---------- Clientes ---------- --}}
        <section class="d-panel" data-reveal="right" data-replay>
            <header class="d-panel__head">
                <div>
                    <h4>Últimos clientes</h4>
                    <p>Quiénes se registraron de últimos</p>
                </div>
                @if (Route::has('customers.index'))
                    <a href="{{ route('customers.index') }}" class="d-more">
                        Ver todos
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                             stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                @endif
            </header>

            @if (count($ultClientes))
                <ul class="d-list" data-stagger="80">
                    @foreach ($ultClientes as $c)
                        <li data-reveal data-replay>
                            <span class="d-list__av" style="--c1:#3E7D5C;--c2:#6BA487">
                                {{ Str::upper(Str::substr($c['nombre'], 0, 1)) }}
                            </span>
                            <span class="d-list__main">
                                <b>{{ $c['nombre'] }}</b>
                                <em>{{ $c['contacto'] }}</em>
                            </span>
                            <span class="d-list__side">
                                <em>{{ $c['fecha'] }}</em>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="d-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                         stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="9" cy="8" r="3.4"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/>
                    </svg>
                    <p>Todavía no tienes clientes registrados.</p>
                    @if (Route::has('customers.create'))
                        <a href="{{ route('customers.create') }}">Registrar uno</a>
                    @endif
                </div>
            @endif
        </section>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         SISTEMA Y CUENTA
         ══════════════════════════════════════════════════════════ --}}
    <div class="d-sec-title" data-reveal>
        <h3>Sistema</h3>
        <span></span>
    </div>

    <div class="d-bottom">
        {{-- ---------- Estado ---------- --}}
        <section class="d-panel" data-reveal data-replay>
            <header class="d-panel__head">
                <div>
                    <h4>Estado del sistema</h4>
                    <p>Salud de la instalación</p>
                </div>
                <span class="d-pulse {{ $sistema['db_viva'] ? 'ok' : 'bad' }}">
                    <i></i>{{ $sistema['db_viva'] ? 'Operativo' : 'Sin conexión' }}
                </span>
            </header>

            <div class="d-ring-row">
                @php
                    $pct  = $sistema['total_mod'] > 0 ? $sistema['migradas'] / $sistema['total_mod'] : 0;
                    $circ = 2 * M_PI * 34;
                @endphp
                <div class="d-ring">
                    <svg viewBox="0 0 80 80">
                        <circle cx="40" cy="40" r="34" fill="none" stroke="#DFE5EC" stroke-width="7"/>
                        <circle cx="40" cy="40" r="34" fill="none" stroke="url(#ringGrad)" stroke-width="7"
                                stroke-linecap="round"
                                transform="rotate(-90 40 40)"
                                stroke-dasharray="{{ round($circ, 1) }}"
                                style="stroke-dashoffset:{{ round($circ, 1) }}"
                                data-bar-ring="{{ round($circ * (1 - $pct), 1) }}"
                                data-ring-total="{{ round($circ, 1) }}"/>
                        <defs>
                            <linearGradient id="ringGrad" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0%" stop-color="#2E6EA8"/>
                                <stop offset="100%" stop-color="#7FB2DE"/>
                            </linearGradient>
                        </defs>
                    </svg>
                    <div class="d-ring__mid">
                        <b data-count="{{ round($pct * 100) }}" data-count-suffix="%" data-count-dur="1500" data-replay>0%</b>
                        <span>migrado</span>
                    </div>
                </div>

                <dl class="d-facts">
                    <div>
                        <dt>Base de datos</dt>
                        <dd>{{ $sistema['db_driver'] }} · {{ $sistema['db_nombre'] }}</dd>
                    </div>
                    <div>
                        <dt>Módulos migrados</dt>
                        <dd>{{ $sistema['migradas'] }} de {{ $sistema['total_mod'] }}</dd>
                    </div>
                    <div>
                        <dt>Laravel</dt>
                        <dd>v{{ $sistema['laravel'] }}</dd>
                    </div>
                    <div>
                        <dt>PHP</dt>
                        <dd>v{{ $sistema['php'] }}</dd>
                    </div>
                    <div>
                        <dt>Entorno</dt>
                        <dd>
                            {{ $sistema['entorno'] }}
                            @if ($sistema['depuracion'])
                                <span class="d-warn">debug activo</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </section>

        {{-- ---------- Cuenta y atajos ---------- --}}
        <section class="d-panel" data-reveal data-replay>
            <header class="d-panel__head">
                <div>
                    <h4>Tu cuenta</h4>
                    <p>Datos y accesos rápidos</p>
                </div>
            </header>

            <div class="d-me">
                <span class="d-me__av">{{ strtoupper(mb_substr($usuario->name, 0, 1)) }}</span>
                <div class="d-me__txt">
                    <b>{{ $usuario->name }}</b>
                    <em>{{ $usuario->email }}</em>
                </div>
            </div>

            <dl class="d-facts d-facts--tight">
                <div>
                    <dt>Miembro desde</dt>
                    <dd>{{ $usuario->created_at?->locale('es')->isoFormat('MMMM YYYY') ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Correo verificado</dt>
                    <dd class="{{ $usuario->email_verified_at ? 'ok' : 'warn' }}">
                        {{ $usuario->email_verified_at ? 'Sí' : 'Pendiente' }}
                    </dd>
                </div>
            </dl>

            <div class="d-quick" data-stagger="70">
                @php
                    $atajos = [
                        ['products.create',   'Nuevo producto'],
                        ['categories.create', 'Nueva categoría'],
                        ['customers.create',  'Nuevo cliente'],
                        ['suppliers.create',  'Nuevo proveedor'],
                    ];
                @endphp
                @foreach ($atajos as [$ruta, $texto])
                    @if (Route::has($ruta))
                        <a href="{{ route($ruta) }}" data-reveal data-replay>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                            {{ $texto }}
                        </a>
                    @endif
                @endforeach
            </div>

            <a href="{{ route('profile.edit') }}" class="d-btn d-btn--full">Editar perfil</a>
        </section>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         ESTILOS
         ══════════════════════════════════════════════════════════ --}}
    @push('styles')
    <style>
        /* ---------- Cabecera ---------- */
        .d-head{ display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:14px; width:100%; }
        .d-head__txt h1{ margin:0; font-size:20px; font-weight:700; letter-spacing:-.02em; color:var(--ink); }
        .d-head__txt p{ margin:3px 0 0; font-size:13px; color:var(--muted); }
        .d-head__chips{ display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
        .d-chip{ display:inline-flex; align-items:center; gap:6px;
                 padding:6px 12px; border-radius:999px; background:#EDF1F6;
                 font-size:11.5px; font-weight:600; color:var(--muted); white-space:nowrap; }
        .d-chip svg{ width:13px; height:13px; }
        .d-chip--blue{ background:#E7EEF6; color:#215480; }
        .d-chip--soft{ background:#fff; border:1px solid var(--line); color:var(--muted); font-weight:500; }

        /* ---------- Banda superior ---------- */
        /* ══════════ Tira de resumen ══════════
           Reemplaza la banda oscura con titular gigante. Las cuatro cifras
           van pegadas en una sola pieza —separadas por una línea, no por
           aire— para que se lean como una tabla y no como cuatro tarjetas
           sueltas. Es lo que hace que se vea deliberado y no de plantilla. */
        .d-tira{ margin-bottom:34px; }

        .d-tira__enc{
            display:flex; align-items:flex-end; justify-content:space-between;
            gap:20px; flex-wrap:wrap;
            padding-bottom:15px; margin-bottom:0;
            border-bottom:1px solid var(--line);
        }
        .d-tira__t{ min-width:0; }
        .d-tira__t h2{ margin:0; font-size:21px; font-weight:650; letter-spacing:-.03em; color:var(--ink); }
        .d-tira__t p{ margin:5px 0 0; font-size:13px; color:var(--muted); }
        .d-tira__cta{ display:flex; gap:9px; flex:none; }

        .d-b{
            display:inline-flex; align-items:center; gap:8px;
            height:38px; padding:0 16px; border-radius:8px; text-decoration:none;
            background:var(--n-850); color:#fff;
            font-size:13px; font-weight:600; white-space:nowrap;
            border:1px solid var(--n-850);
            transition:background .22s ease, border-color .22s ease, transform .16s var(--e-soft);
        }
        .d-b svg{ width:14px; height:14px; transition:transform .34s var(--e-back); }
        .d-b:hover{ background:var(--n-900); transform:translateY(-1px); }
        .d-b:hover svg{ transform:rotate(90deg); }
        .d-b--g{ background:var(--card); color:var(--ink-2); border-color:var(--line); }
        .d-b--g:hover{ background:var(--line-2); border-color:var(--muted-2); transform:translateY(-1px); }

        .d-tira__cifras{
            display:grid; grid-template-columns:repeat(4,minmax(0,1fr));
            border:1px solid var(--line); border-top:0;
            border-radius:0 0 11px 11px; overflow:hidden; background:var(--card);
        }
        .d-tira__c{ padding:17px 20px; border-left:1px solid var(--line-2); }
        .d-tira__c:first-child{ border-left:0; }
        .d-tira__c span{
            display:block; font-size:10.5px; letter-spacing:.14em; text-transform:uppercase;
            color:var(--muted-2); font-weight:650;
        }
        .d-tira__c b{
            display:block; margin-top:9px; font-size:27px; font-weight:600;
            letter-spacing:-.035em; line-height:1; color:var(--ink);
        }

        @media (max-width:900px){
            .d-tira__cifras{ grid-template-columns:repeat(2,minmax(0,1fr)); }
            .d-tira__c:nth-child(3){ border-left:0; }
            .d-tira__c:nth-child(n+3){ border-top:1px solid var(--line-2); }
        }
        @media (max-width:560px){
            .d-tira__enc{ align-items:flex-start; }
            .d-tira__cta{ width:100%; }
            .d-tira__cta .d-b{ flex:1; justify-content:center; }
            .d-tira__cifras{ grid-template-columns:minmax(0,1fr); }
            .d-tira__c{ border-left:0; border-top:1px solid var(--line-2); }
            .d-tira__c:first-child{ border-top:0; }
            .d-tira__c b{ font-size:24px; }
        }

        /* ---------- Títulos de sección ---------- */
        .d-sec-title{ display:flex; align-items:center; gap:16px; margin:0 0 16px; }
        .d-sec-title h3{ margin:0; font-size:13px; font-weight:700; letter-spacing:.16em;
                         text-transform:uppercase; color:var(--muted); white-space:nowrap; }
        .d-sec-title span{ flex:1; height:1px;
                           background:linear-gradient(90deg,var(--line),transparent); }

        /* ---------- Tarjetas KPI ---------- */
        .d-grid{ display:grid; gap:16px; grid-template-columns:repeat(4,minmax(0,1fr)); margin-bottom:34px; }

        .d-card{
            position:relative; overflow:hidden; display:block; text-decoration:none;
            padding:18px 18px 0; border-radius:9px;
            background:#fff; border:1px solid var(--line);
            transition:transform .4s var(--e-soft), box-shadow .4s ease, border-color .3s ease;
        }
        .d-card:hover{ transform:translateY(-6px);
                       box-shadow:0 26px 46px -26px rgba(16,24,37,.42); border-color:transparent; }
        .d-card.is-off{ opacity:.55; pointer-events:none; }

        .d-card__top{ position:absolute; inset:0 0 auto 0; height:3px;
                      background:linear-gradient(90deg,var(--c1),var(--c2));
                      transform:scaleX(0); transform-origin:left;
                      transition:transform .55s var(--e-soft); }
        .d-card:hover .d-card__top{ transform:scaleX(1); }

        .d-card__row{ display:flex; align-items:flex-start; justify-content:space-between; }
        .d-card__ico{ width:40px; height:40px; border-radius:9px; display:grid; place-items:center;
                      background:var(--soft); color:var(--c1);
                      transition:transform .42s var(--e-back), background .3s ease, color .3s ease; }
        .d-card:hover .d-card__ico{ transform:scale(1.12) rotate(-6deg);
                                    background:linear-gradient(135deg,var(--c1),var(--c2)); color:#fff; }
        .d-card__ico svg{ width:18px; height:18px; }

        .d-card__arrow{ color:#C3CCD8; transition:transform .32s var(--e-soft), color .25s ease; }
        .d-card__arrow svg{ width:16px; height:16px; }
        .d-card:hover .d-card__arrow{ transform:translateX(4px); color:var(--c1); }

        .d-card__delta{ display:inline-flex; align-items:center; gap:3px;
                        padding:4px 8px; border-radius:999px;
                        font-size:11px; font-weight:700; font-variant-numeric:tabular-nums; }
        .d-card__delta svg{ width:11px; height:11px; }
        .d-card__delta.up{ background:#EDF5F0; color:#3E7D5C; }
        .d-card__delta.down{ background:#FBF0F0; color:#96504F; }

        .d-card__num{ margin-top:15px; }
        .d-card__num b{ font-size:30px; font-weight:700; letter-spacing:-.035em;
                        color:var(--ink); font-variant-numeric:tabular-nums; }
        .d-card__num em{ font-style:normal; font-size:28px; font-weight:700; color:#C3CCD8; }
        .d-card__lbl{ margin-top:3px; font-size:13.5px; font-weight:600; color:var(--ink-2); }
        .d-card__hint{ margin-top:2px; font-size:11.5px; color:var(--muted-2); }
        .d-card__spark{ display:block; width:calc(100% + 38px); height:46px; margin:10px -19px 0; }

        /* ---------- Paneles ---------- */
        .d-charts{ display:grid; gap:16px; grid-template-columns:minmax(0,1.45fr) minmax(0,1fr); margin-bottom:34px; }
        .d-bottom{ display:grid; gap:16px; grid-template-columns:minmax(0,1.35fr) minmax(0,1fr);
                   align-items:start; }

        .d-panel{
            background:#fff; border:1px solid var(--line); border-radius:10px; padding:22px 22px 24px;
            transition:box-shadow .4s ease, transform .4s var(--e-soft);
        }
        .d-panel:hover{ box-shadow:0 22px 44px -30px rgba(16,24,37,.35); }
        .d-panel__head{ display:flex; align-items:flex-start; justify-content:space-between; gap:14px; margin-bottom:18px; }
        .d-panel__head h4{ margin:0; font-size:15px; font-weight:700; color:var(--ink); letter-spacing:-.01em; }
        .d-panel__head p{ margin:3px 0 0; font-size:12.2px; color:var(--muted-2); }

        .d-legend{ display:flex; gap:14px; flex-wrap:wrap; }
        .d-legend span{ display:inline-flex; align-items:center; gap:6px; font-size:11.5px; color:var(--muted); }
        .d-legend i{ width:9px; height:9px; border-radius:3px; }

        .d-more{ display:inline-flex; align-items:center; gap:5px; flex:none;
                 font-size:12.2px; font-weight:600; color:var(--a-500); text-decoration:none;
                 transition:gap .25s var(--e-soft), color .2s ease; }
        .d-more svg{ width:13px; height:13px; }
        .d-more:hover{ gap:9px; color:var(--a-600); }

        /* ---------- Gráfica de área ---------- */
        .d-chart{ position:relative; }
        .d-chart__svg{ display:block; width:100%; height:210px; overflow:visible; }
        .d-chart__dots{ position:absolute; inset:0; pointer-events:none; }
        .d-dot{ position:absolute; width:9px; height:9px; margin:-4.5px 0 0 -4.5px;
                border-radius:50%; background:#fff; border:2.4px solid #2E6EA8;
                box-shadow:0 2px 8px rgba(46,110,168,.45);
                opacity:0; transform:scale(0);
                animation:dotIn .5s var(--e-back) forwards; animation-delay:var(--dl); }
        @keyframes dotIn{ to{ opacity:1; transform:scale(1); } }
        /* Eje vertical del área: cuántos registros vale cada línea */
        .d-chart__y{ position:absolute; inset:0 auto 0 0; width:100%; pointer-events:none; }
        .d-chart__y span{
            position:absolute; left:0; transform:translateY(-50%);
            padding-right:6px;
            font-size:10px; color:var(--muted-2); font-variant-numeric:tabular-nums;
            background:linear-gradient(90deg,var(--card) 72%,transparent);
        }

        .d-chart__x{ display:flex; justify-content:space-between; margin-top:4px;
                     font-size:11px; color:var(--muted-2); }
        .d-chart__empty{
            position:absolute; inset:0; display:flex; flex-direction:column;
            align-items:center; justify-content:center; gap:9px;
            background:rgba(255,255,255,.7); backdrop-filter:blur(1.5px);
            font-size:12.5px; color:var(--muted-2); border-radius:10px;
        }
        .d-chart__empty svg{ width:26px; height:26px; opacity:.5; }

        /* ---------- Barras ---------- */
        .d-bars{ list-style:none; margin:0; padding:0; display:grid; gap:15px; }
        .d-bars__top{ display:flex; align-items:baseline; justify-content:space-between; gap:10px; margin-bottom:7px; }
        .d-bars__name{ font-size:13px; font-weight:500; color:var(--ink-2);
                       overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .d-bars__val{ font-size:13px; font-weight:700; color:var(--ink); font-variant-numeric:tabular-nums; }
        .d-bars__track{ height:8px; border-radius:99px; background:#EDF1F6; overflow:hidden; }
        .d-bars__track i{ display:block; height:100%; border-radius:99px;
                          background:linear-gradient(90deg,#2E6EA8,#7FB2DE);
                          box-shadow:0 2px 10px -2px rgba(46,110,168,.6); }

        /* ---------- Listas ---------- */
        .d-list{ list-style:none; margin:0; padding:0; }
        .d-list li{ display:flex; align-items:center; gap:12px; padding:11px 10px;
                    border-radius:9px; transition:background .22s ease, transform .28s var(--e-soft); }
        .d-list li:hover{ background:var(--paper); transform:translateX(3px); }
        .d-list li + li{ border-top:1px solid #EDF1F6; }
        .d-list__av{ width:38px; height:38px; flex:none; border-radius:9px;
                     display:grid; place-items:center; color:#fff; font-size:14px; font-weight:700;
                     background:linear-gradient(135deg,var(--c1),var(--c2));
                     box-shadow:0 8px 18px -10px var(--c1); }
        .d-list__main{ flex:1; min-width:0; }
        .d-list__main b{ display:block; font-size:13.4px; font-weight:600; color:var(--ink);
                         white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .d-list__main em{ display:block; font-style:normal; font-size:11.6px; color:var(--muted-2);
                          white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px; }
        .d-list__side{ flex:none; text-align:right; }
        .d-list__side b{ display:block; font-size:13px; font-weight:700; color:var(--ink);
                         font-variant-numeric:tabular-nums; }
        .d-list__side em{ display:block; font-style:normal; font-size:11px; color:var(--muted-2); margin-top:2px; }
        .d-list__side em.ok{ color:#3E7D5C; font-weight:600; }
        .d-list__side em.off{ color:#96504F; font-weight:600; }

        /* ---------- Vacíos ---------- */
        .d-empty{ display:flex; flex-direction:column; align-items:center; justify-content:center;
                  gap:9px; padding:34px 16px; text-align:center; color:var(--muted-2); }
        .d-empty svg{ width:32px; height:32px; opacity:.45; }
        .d-empty p{ margin:0; font-size:13px; }
        .d-empty a{ font-size:12.5px; font-weight:600; color:var(--a-500); text-decoration:none; }
        .d-empty a:hover{ text-decoration:underline; }

        /* ---------- Estado del sistema ---------- */
        .d-pulse{ display:inline-flex; align-items:center; gap:7px; flex:none;
                  padding:6px 12px; border-radius:999px; font-size:11.5px; font-weight:600; }
        .d-pulse i{ width:7px; height:7px; border-radius:50%; }
        .d-pulse.ok{ background:#EDF5F0; color:#3E7D5C; }
        .d-pulse.ok i{ background:#3E7D5C; box-shadow:0 0 0 0 rgba(62,125,92,.55); animation:beat 1.9s infinite; }
        .d-pulse.bad{ background:#FBF0F0; color:#96504F; }
        .d-pulse.bad i{ background:#96504F; }
        @keyframes beat{ 70%{ box-shadow:0 0 0 9px rgba(62,125,92,0); } 100%{ box-shadow:0 0 0 0 rgba(62,125,92,0); } }

        .d-ring-row{ display:flex; align-items:center; gap:26px; flex-wrap:wrap; }
        .d-ring{ position:relative; width:106px; height:106px; flex:none; }
        .d-ring svg{ width:100%; height:100%; }
        .d-ring svg circle[data-bar-ring]{ transition:stroke-dashoffset 1.5s var(--e-soft) .2s; }
        .d-ring__mid{ position:absolute; inset:0; display:grid; place-content:center; text-align:center; }
        .d-ring__mid b{ display:block; font-size:19px; font-weight:700; color:var(--ink);
                        font-variant-numeric:tabular-nums; }
        .d-ring__mid span{ display:block; font-size:9.5px; letter-spacing:.14em;
                           text-transform:uppercase; color:var(--muted-2); margin-top:1px; }

        .d-facts{ flex:1; min-width:210px; margin:0; display:grid; gap:9px; }
        .d-facts--tight{ margin-top:16px; }
        .d-facts > div{ display:flex; align-items:baseline; justify-content:space-between; gap:12px;
                        padding-bottom:8px; border-bottom:1px dashed #EDF1F6; }
        .d-facts > div:last-child{ border-bottom:none; padding-bottom:0; }
        .d-facts dt{ font-size:12.5px; color:var(--muted); }
        .d-facts dd{ margin:0; font-size:12.8px; font-weight:600; color:var(--ink-2); text-align:right; }
        .d-facts dd.ok{ color:#3E7D5C; }
        .d-facts dd.warn{ color:#96703C; }
        .d-warn{ display:inline-block; margin-left:6px; padding:2px 7px; border-radius:6px;
                 background:#FAF5EC; color:#96703C; font-size:10.5px; font-weight:700; }

        /* ---------- Cuenta ---------- */
        .d-me{ display:flex; align-items:center; gap:13px; }
        .d-me__av{ width:50px; height:50px; flex:none; border-radius:11px;
                   display:grid; place-items:center; color:#fff; font-size:19px; font-weight:700;
                   background:linear-gradient(135deg,#2E6EA8,#7FB2DE);
                   box-shadow:0 12px 24px -12px rgba(46,110,168,.9); }
        .d-me__txt b{ display:block; font-size:14.5px; font-weight:700; color:var(--ink); }
        .d-me__txt em{ display:block; font-style:normal; font-size:12px; color:var(--muted-2); margin-top:2px; }

        .d-quick{ display:grid; grid-template-columns:1fr 1fr; gap:9px; margin-top:18px; }
        .d-quick a{ display:flex; align-items:center; gap:8px; padding:10px 12px;
                    border:1px solid var(--line); border-radius:9px; text-decoration:none;
                    font-size:12.4px; font-weight:600; color:var(--ink-2);
                    transition:border-color .25s ease, background .25s ease, transform .3s var(--e-soft); }
        .d-quick a:hover{ border-color:#B7CADD; background:#F2F6FA; transform:translateY(-2px); }
        .d-quick svg{ width:13px; height:13px; flex:none; color:var(--a-500);
                      transition:transform .32s var(--e-back); }
        .d-quick a:hover svg{ transform:rotate(90deg); }

        /* ---------- Responsive ---------- */
        @media (max-width:1280px){
            .d-grid{ grid-template-columns:repeat(3,minmax(0,1fr)); }
        }
        @media (max-width:1100px){
            .d-charts, .d-bottom{ grid-template-columns:minmax(0,1fr); }
        }
        @media (max-width:820px){
            .d-grid{ grid-template-columns:repeat(2,minmax(0,1fr)); }
            .d-panel{ padding:18px 16px 20px; }
        }
        @media (max-width:520px){
            .d-grid{ grid-template-columns:minmax(0,1fr); }
            .d-quick{ grid-template-columns:minmax(0,1fr); }
            .d-ring-row{ gap:18px; }
        }

        /* ---------- Inventario ---------- */
        .d-inv{ display:grid; gap:16px; grid-template-columns:minmax(0,1fr) minmax(0,1fr);
                margin-bottom:26px; align-items:start; }
        @media (max-width:960px){ .d-inv{ grid-template-columns:minmax(0,1fr); } }

        .d-inv__cifras{ display:grid; gap:12px; grid-template-columns:1fr 1fr; padding:4px 0 2px; }
        .d-inv__c{ padding:14px 15px; border:1px solid var(--line); border-radius:12px;
                   background:var(--paper); }
        .d-inv__c span{ display:block; font-size:11.1px; letter-spacing:.05em; text-transform:uppercase;
                        color:var(--muted-2); font-weight:600; }
        .d-inv__c b{ display:block; margin:6px 0 3px; font-size:21px; font-weight:700;
                     letter-spacing:-.03em; color:var(--ink); line-height:1.05;
                     font-variant-numeric:tabular-nums; }
        .d-inv__c b.ok{ color:var(--ok); }
        .d-inv__c em{ display:block; font-style:normal; font-size:11.4px; color:var(--muted); }
        @media (max-width:520px){ .d-inv__cifras{ grid-template-columns:minmax(0,1fr); } }

        .d-rep{ list-style:none; margin:0; padding:2px 0 0; display:grid; gap:14px; }
        .d-rep__top{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px;
                     margin-bottom:7px; }
        .d-rep__name{ min-width:0; }
        .d-rep__name a, .d-rep__name{ font-size:13px; font-weight:600; color:var(--ink);
                                      text-decoration:none; }
        .d-rep__name a:hover{ color:var(--a-600); }
        .d-rep__name em{ display:block; font-style:normal; font-size:11.4px; color:var(--muted);
                         font-weight:500; margin-top:1px; }
        .d-rep__val{ font-size:12.2px; font-weight:700; white-space:nowrap;
                     font-variant-numeric:tabular-nums; }
        .d-rep__val.warn{ color:var(--warn); }
        .d-rep__val.bad{ color:var(--bad); }
        .d-rep__track{ height:6px; border-radius:99px; background:var(--line-2); overflow:hidden; }
        .d-rep__track i{ display:block; height:100%; border-radius:99px;
                         transition:width 1.05s var(--e-soft); }
        .d-rep__track i.warn{ background:linear-gradient(90deg,#B08947,var(--warn)); }
        .d-rep__track i.bad{ background:linear-gradient(90deg,#B06E6D,var(--bad)); }

        /* ---------- Ventas ---------- */
        .d-ven{ display:grid; gap:16px; grid-template-columns:minmax(0,1fr) minmax(0,1fr);
                margin-bottom:26px; align-items:start; }
        @media (max-width:960px){ .d-ven{ grid-template-columns:minmax(0,1fr); } }

        .d-ven__cifras{ display:grid; gap:12px; grid-template-columns:1fr 1fr; padding:4px 0 2px; }
        @media (max-width:520px){ .d-ven__cifras{ grid-template-columns:minmax(0,1fr); } }
        .d-ven__c{ padding:14px 15px; border:1px solid var(--line); border-radius:12px;
                   background:var(--paper); }
        .d-ven__c--hoy{ background:linear-gradient(150deg,var(--n-850),var(--n-900));
                        border-color:var(--n-700); }
        .d-ven__c--hoy span{ color:var(--a-300) !important; }
        .d-ven__c--hoy b{ color:#fff !important; }
        .d-ven__c--hoy em{ color:#8FA3BC !important; }
        .d-ven__c span{ display:block; font-size:11.1px; letter-spacing:.05em; text-transform:uppercase;
                        color:var(--muted-2); font-weight:600; }
        .d-ven__c b{ display:block; margin:6px 0 3px; font-size:21px; font-weight:700;
                     letter-spacing:-.03em; color:var(--ink); line-height:1.05;
                     font-variant-numeric:tabular-nums; }
        .d-ven__c b.ok{ color:var(--ok); }
        .d-ven__c em{ display:block; font-style:normal; font-size:11.4px; color:var(--muted); }

    </style>



    @endpush

    @push('scripts')
    <script>
    /* Cruz de referencia y tooltip sobre la gráfica de área. */
    (function () {
        var caja  = document.getElementById('dChart');
        var cruz  = document.getElementById('dCross');
        var tip   = document.getElementById('dTip');
        if (!caja || !cruz || !tip) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        var serie = [], pts = [];
        try {
            serie = JSON.parse(caja.getAttribute('data-serie')) || [];
            pts   = JSON.parse(caja.getAttribute('data-pts')) || [];
        } catch (e) { return; }
        if (!serie.length || serie.length !== pts.length) return;

        function cercano(pctX) {
            var mejor = 0, dist = Infinity;
            for (var i = 0; i < pts.length; i++) {
                var d = Math.abs(pts[i] - pctX);
                if (d < dist) { dist = d; mejor = i; }
            }
            return mejor;
        }

        function mostrar(e) {
            var r = caja.getBoundingClientRect();
            var pctX = (e.clientX - r.left) / r.width * 100;
            var i = cercano(pctX);
            var x = pts[i];

            cruz.style.left = x + '%';
            cruz.classList.add('on');

            tip.innerHTML =
                '<b></b>' +
                '<div class="r"><i style="background:#2E6EA8"></i><span>Productos</span><em class="p"></em></div>' +
                '<div class="r"><i style="background:#7FB2DE"></i><span>Clientes</span><em class="c"></em></div>';
            tip.querySelector('b').textContent = serie[i].e;
            tip.querySelector('.p').textContent = serie[i].p;
            tip.querySelector('.c').textContent = serie[i].c;

            /* No se sale por los bordes del panel */
            var izq = Math.min(Math.max(x, 12), 88);
            tip.style.left = izq + '%';
            tip.style.top = '46%';
            tip.classList.add('on');
        }

        function ocultar() {
            cruz.classList.remove('on');
            tip.classList.remove('on');
        }

        caja.addEventListener('pointermove', mostrar);
        caja.addEventListener('pointerleave', ocultar);
    })();
    </script>

    <script>
    /* Anillo de progreso del estado del sistema. */
    (function () {
        var ring = document.querySelector('[data-bar-ring]');
        if (!ring) return;
        var target = ring.getAttribute('data-bar-ring');
        var total  = ring.getAttribute('data-ring-total');
        var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function fill() { ring.style.strokeDashoffset = target; }
        function reset() { ring.style.strokeDashoffset = total; }

        if (reduced || !('IntersectionObserver' in window)) { fill(); return; }

        new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (e.isIntersecting) setTimeout(fill, 180);
                else if (e.boundingClientRect.top > 0) reset();
            });
        }, { threshold: 0.4 }).observe(ring);
    })();
    </script>
    @endpush
</x-app-layout>
