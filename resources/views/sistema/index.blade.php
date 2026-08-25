@php
    use App\Support\Formato;

    $filtros = [
        'todas'       => 'Todas',
        'clientes'    => 'Clientes',
        'pruebas'     => 'Pruebas',
        'suspendidas' => 'Suspendidas',
        'vencidas'    => 'Vencidas',
    ];
@endphp

<x-mus.page title="Todos los negocios" subtitle="Lo que hay en el sistema, cliente por cliente" icon="grid">

    {{-- ══════════ El pulso ══════════ --}}
    <div class="msis__tiras">
        <div class="msis__t">
            <span>Clientes activos</span>
            <b>{{ number_format($resumen['clientes'], 0, ',', '.') }}</b>
        </div>
        <div class="msis__t {{ $resumen['pruebas'] ? 'is-vivo' : '' }}">
            <span>Pruebas corriendo</span>
            <b>{{ number_format($resumen['pruebas'], 0, ',', '.') }}</b>
        </div>
        <div class="msis__t">
            <span>MRR</span>
            <b>{{ Formato::moneda($resumen['mrr']) }}</b>
        </div>
        <div class="msis__t">
            <span>ARR</span>
            <b>{{ Formato::moneda($resumen['arr']) }}</b>
        </div>
        <div class="msis__t {{ $resumen['suspendidas'] ? 'is-malo' : '' }}">
            <span>Suspendidas</span>
            <b>{{ number_format($resumen['suspendidas'], 0, ',', '.') }}</b>
        </div>
    </div>

    {{-- ══════════ Cómo se movió el ingreso ══════════ --}}
    <x-mus.panel title="Este mes" sub="De dónde salió y a dónde se fue el ingreso" :reveal="true">
        @php
            $lineas = [
                ['Clientes nuevos', $mes['nuevo'],        'mas'],
                ['Expansión',       $mes['expansion'],    'mas'],
                ['Reactivaciones',  $mes['reactivacion'], 'mas'],
                ['Contracción',     $mes['contraccion'],  'menos'],
                ['Bajas',           $mes['baja'],         'menos'],
            ];
        @endphp

        <div class="msis__mov">
            @foreach ($lineas as [$texto, $valor, $signo])
                <div class="msis__m {{ (float) $valor == 0.0 ? 'is-cero' : '' }}">
                    <span>{{ $texto }}</span>
                    <b class="msis__{{ $signo }}">
                        {{ $signo === 'mas' && $valor > 0 ? '+' : '' }}{{ Formato::moneda($valor) }}
                    </b>
                </div>
            @endforeach

            <div class="msis__m msis__m--neto">
                <span>Neto del mes</span>
                <b class="{{ $mes['neto'] >= 0 ? 'msis__mas' : 'msis__menos' }}">
                    {{ $mes['neto'] > 0 ? '+' : '' }}{{ Formato::moneda($mes['neto']) }}
                </b>
            </div>
        </div>

        @if ($mes['neto'] < 0 && $mes['nuevo'] > 0)
            <p class="msis__aviso">
                Entraron clientes nuevos y aun así el ingreso bajó. Eso lo explican la
                contracción y las bajas, no la venta: mira quién se bajó de plan antes
                de salir a buscar más clientes.
            </p>
        @endif
    </x-mus.panel>

    {{-- ══════════ Los negocios ══════════ --}}
    <x-mus.panel :pad="false" :reveal="true">
        <div class="msis__barra">
            <div class="msis__filtros">
                @foreach ($filtros as $clave => $texto)
                    <a href="{{ route('sistema.index', ['ver' => $clave, 'q' => $q]) }}"
                       class="{{ $filtro === $clave ? 'is-on' : '' }}">{{ $texto }}</a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('sistema.index') }}" class="msis__buscar">
                <input type="hidden" name="ver" value="{{ $filtro }}">
                <input type="search" name="q" value="{{ $q }}"
                       placeholder="Buscar por nombre, correo o NIT…">
            </form>
        </div>

        @if ($empresas->total())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Negocio</th>
                            <th>Estado</th>
                            <th>Plan</th>
                            <th>Aporta</th>
                            <th>Locales</th>
                            <th>Gente</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($empresas as $e)
                            <tr data-row>
                                <td>
                                    <a href="{{ route('sistema.empresa', $e) }}" class="msis__nom">
                                        <b>{{ $e->nombre }}</b>
                                        <em>{{ $e->correo ?: 'sin correo' }}</em>
                                    </a>
                                </td>
                                <td>
                                    @if ($e->es_demo)
                                        @php $min = $e->minutosRestantes(); @endphp
                                        @if ($min === null || $min > 0)
                                            <x-mus.badge tone="info" :dot="true">
                                                Prueba · {{ $min === null ? 'sin límite' : $min . ' min' }}
                                            </x-mus.badge>
                                        @else
                                            <x-mus.badge tone="off">Prueba vencida</x-mus.badge>
                                        @endif
                                    @elseif ($e->estaActiva())
                                        <x-mus.badge tone="ok" :dot="true">Activa</x-mus.badge>
                                    @else
                                        <x-mus.badge tone="off">Suspendida</x-mus.badge>
                                    @endif
                                </td>
                                <td>{{ $e->plan?->nombre ?? '—' }}</td>
                                <td>{{ Formato::moneda($e->mrr()) }}</td>
                                <td>{{ $e->tiendas_count }}</td>
                                <td>{{ $e->usuarios_count }}</td>
                                <td class="act">
                                    <span class="mt__acts">
                                        <form method="POST" action="{{ route('sistema.entrar', $e) }}" data-sin-ctx>
                                            @csrf
                                            <x-mus.btn type="submit" variant="ghost" :sm="true"
                                                       class="mb--icon" title="Entrar a mirarlo">
                                                <x-mus.icon name="eye" :w="15" />
                                            </x-mus.btn>
                                        </form>
                                        <x-mus.btn href="{{ route('sistema.empresa', $e) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Ficha">
                                            <x-mus.icon name="pencil" :w="15" />
                                        </x-mus.btn>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$empresas" label="negocios" />
        @else
            <x-mus.empty icon="grid" title="No hay nada aquí"
                         text="Con este filtro no aparece ningún negocio. Prueba con «Todas»." />
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        /* minmax(0,…): con `1fr` la tira de la cifra más larga ensancha su
           columna y las cinco dejan de medir lo mismo. */
        .msis__tiras{ display:grid; grid-template-columns:repeat(auto-fit, minmax(min(160px,100%), 1fr));
                      gap:11px; margin-bottom:16px; }
        .msis__t{ padding:13px 15px; border:1px solid var(--line); border-radius:12px;
                  background:var(--card); }
        .msis__t span{ display:block; font-size:10.8px; font-weight:700; letter-spacing:.07em;
                       text-transform:uppercase; color:var(--muted); }
        .msis__t b{ display:block; margin-top:5px; font-size:21px; color:var(--ink);
                    font-variant-numeric:tabular-nums; }
        .msis__t.is-vivo b{ color:var(--a-500); }
        .msis__t.is-malo b{ color:var(--bad); }

        .msis__mov{ display:grid; grid-template-columns:repeat(auto-fit, minmax(min(150px,100%), 1fr));
                    gap:12px; }
        .msis__m{ padding:10px 0; border-top:2px solid var(--line-2); }
        .msis__m span{ display:block; font-size:11.8px; color:var(--muted); }
        .msis__m b{ display:block; margin-top:3px; font-size:15.5px;
                    font-variant-numeric:tabular-nums; }
        .msis__m.is-cero b{ color:var(--muted-2); }
        .msis__m--neto{ border-top-color:var(--a-500); }
        .msis__mas{ color:var(--ok); }
        .msis__menos{ color:var(--bad); }
        .msis__aviso{ margin:14px 0 0; max-width:70ch; padding:11px 14px; border-radius:10px;
                      background:rgba(150,112,60,.09); border:1px solid rgba(150,112,60,.24);
                      font-size:12.4px; line-height:1.6; color:var(--ink-2); }

        .msis__barra{ display:flex; align-items:center; justify-content:space-between; gap:14px;
                      flex-wrap:wrap; padding:11px 15px; border-bottom:1px solid var(--line-2);
                      background:var(--paper); }
        .msis__filtros{ display:flex; gap:5px; flex-wrap:wrap; }
        .msis__filtros a{ padding:5px 11px; border-radius:99px; font-size:12.2px;
                          color:var(--muted); text-decoration:none;
                          transition:background .16s, color .16s; }
        .msis__filtros a:hover{ background:var(--line-2); color:var(--ink-2); }
        .msis__filtros a.is-on{ background:var(--a-500); color:#fff; }
        .msis__buscar input{ min-width:min(250px,100%); padding:7px 12px; border-radius:9px;
                             border:1px solid var(--line); background:var(--card);
                             color:var(--ink); font-size:12.6px; font-family:inherit; }
        .msis__buscar input:focus{ outline:none; border-color:var(--a-500); }

        .msis__nom{ display:block; text-decoration:none; color:inherit; min-width:0; }
        .msis__nom b{ display:block; color:var(--ink); }
        .msis__nom em{ display:block; font-style:normal; font-size:11.4px; color:var(--muted); }
    </style>
    @endpush
</x-mus.page>
