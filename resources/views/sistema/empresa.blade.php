@php
    use App\Support\Formato;

    $nombres = ['tiendas' => 'Locales', 'usuarios' => 'Usuarios',
                'productos' => 'Productos', 'ventas_mes' => 'Ventas del mes'];
@endphp

<x-mus.page :title="$empresa->nombre" subtitle="Ficha del cliente" icon="grid"
            :crumbs="['Todos los negocios' => route('sistema.index'), $empresa->nombre => null]">

    <x-slot name="actions">
        <form method="POST" action="{{ route('sistema.entrar', $empresa) }}" data-sin-ctx>
            @csrf
            <x-mus.btn type="submit" variant="primary" icon="eye">Entrar a mirarlo</x-mus.btn>
        </form>
    </x-slot>

    {{-- ══════════ De un vistazo ══════════ --}}
    <x-mus.panel :reveal="true">
        <div class="mfic__cab">
            <div>
                <div class="mfic__estado">
                    @if ($empresa->es_demo)
                        @php $min = $empresa->minutosRestantes(); @endphp
                        @if ($min === null || $min > 0)
                            <x-mus.badge tone="info" :dot="true">
                                Prueba · quedan {{ $min === null ? '∞' : $min . ' min' }}
                            </x-mus.badge>
                        @else
                            <x-mus.badge tone="off">Prueba vencida</x-mus.badge>
                        @endif
                    @elseif ($empresa->estaActiva())
                        <x-mus.badge tone="ok" :dot="true">Activa</x-mus.badge>
                    @else
                        <x-mus.badge tone="off">Suspendida</x-mus.badge>
                    @endif

                    <span class="mfic__rubro">{{ ucfirst($empresa->rubro) }}</span>
                </div>

                <dl class="mfic__datos">
                    <div><dt>Dueño</dt><dd>{{ $dueno?->name ?? '—' }}</dd></div>
                    <div><dt>Correo</dt><dd>{{ $empresa->correo ?: '—' }}</dd></div>
                    <div><dt>Teléfono</dt><dd>{{ $empresa->telefono ?: '—' }}</dd></div>
                    <div><dt>NIT</dt><dd>{{ $empresa->nit ?: '—' }}</dd></div>
                    <div><dt>Alta</dt><dd>{{ Formato::enPalabras($empresa->created_at, 'D MMM YYYY') }}</dd></div>
                    <div><dt>Cliente desde</dt>
                         <dd>{{ Formato::enPalabras($empresa->suscrita_desde, 'D MMM YYYY', '—') }}</dd></div>
                </dl>
            </div>

            <div class="mfic__aporta">
                <b>{{ Formato::moneda($aporte) }}</b>
                <span>al mes{{ $empresa->periodo === 'anual' ? ' (paga el año)' : '' }}</span>
                @if ($empresa->precio_pactado !== null)
                    <em>precio pactado</em>
                @endif
            </div>
        </div>
    </x-mus.panel>

    {{-- ══════════ Cupo ══════════ --}}
    <x-mus.panel title="Cómo va de cupo" sub="Contra los topes de su plan" :reveal="true">
        <div class="mfic__cupo">
            @foreach ($panorama as $recurso => $dato)
                <div class="{{ $dato['apretado'] ? 'is-apretado' : '' }}">
                    <span>
                        <b>{{ $nombres[$recurso] ?? $recurso }}</b>
                        {{ number_format($dato['usado'], 0, ',', '.') }}
                        de {{ $dato['limite'] === null ? '∞' : number_format($dato['limite'], 0, ',', '.') }}
                    </span>
                    <div class="mfic__barra"><i style="width:{{ $dato['porcentaje'] ?? 0 }}%"></i></div>
                </div>
            @endforeach
        </div>
    </x-mus.panel>

    {{-- ══════════ Suscripción ══════════ --}}
    <x-mus.panel title="Suscripción" sub="Lo que se le cobra, y por qué" :reveal="true">
        <form method="POST" action="{{ route('sistema.suscribir', $empresa) }}" class="mfic__form">
            @csrf

            <label>
                <span>Plan</span>
                <select name="plan_id">
                    <option value="">Sin plan (sin topes)</option>
                    @foreach ($planes as $p)
                        <option value="{{ $p->id }}" @selected($empresa->plan_id === $p->id)>
                            {{ $p->nombre }} · {{ Formato::moneda($p->precio_mensual) }}/mes
                        </option>
                    @endforeach
                </select>
            </label>

            <label>
                <span>Cómo paga</span>
                <select name="periodo">
                    <option value="mensual" @selected($empresa->periodo !== 'anual')>Mes a mes</option>
                    <option value="anual" @selected($empresa->periodo === 'anual')>El año (−20%)</option>
                </select>
            </label>

            <label>
                <span>Precio pactado <small>opcional</small></span>
                <input type="number" name="precio_pactado" step="1" min="0"
                       value="{{ old('precio_pactado', $empresa->precio_pactado) }}"
                       placeholder="Déjalo vacío para usar el del plan">
            </label>

            <label>
                <span>Sedes contratadas</span>
                <input type="number" name="tiendas" min="1" max="99"
                       value="{{ old('tiendas', $empresa->tiendas_contratadas ?? 1) }}">
            </label>

            <label class="mfic__ancho">
                <span>Motivo <small>queda en el historial</small></span>
                <input type="text" name="motivo" maxlength="200"
                       placeholder="Subió a Pro porque abrió la sede del norte">
            </label>

            <div class="mfic__ancho">
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar suscripción</x-mus.btn>
            </div>
        </form>
    </x-mus.panel>

    {{-- ══════════ Acciones ══════════ --}}
    <x-mus.panel title="Acciones" sub="Cosas que cambian el estado de la cuenta" :reveal="true">
        <div class="mfic__acciones">

            @if ($empresa->es_demo)
                <form method="POST" action="{{ route('sistema.extender', $empresa) }}">
                    @csrf
                    <b>Darle más tiempo a la prueba</b>
                    <p>Se suma a lo que le quede. Si ya venció, cuenta desde ahora.</p>
                    <div class="mfic__fila">
                        <input type="number" name="minutos" value="150" min="15" max="10080">
                        <span>minutos</span>
                        <x-mus.btn type="submit" icon="plus">Extender</x-mus.btn>
                    </div>
                </form>
            @endif

            @if ($empresa->estado === 'activa')
                <form method="POST" action="{{ route('sistema.suspender', $empresa) }}">
                    @csrf
                    <b>Suspender la cuenta</b>
                    <p>No entra nadie, pero <strong>no se borra nada</strong>. Si vuelve, encuentra todo donde lo dejó.</p>
                    <div class="mfic__fila">
                        <input type="text" name="motivo" maxlength="200" placeholder="Motivo (cerró el local, no pagó…)">
                        <x-mus.btn type="submit" variant="danger" icon="close">Suspender</x-mus.btn>
                    </div>
                </form>
            @else
                <form method="POST" action="{{ route('sistema.reactivar', $empresa) }}">
                    @csrf
                    <b>Reactivar</b>
                    <p>Se anota como reactivación y no como cliente nuevo: traerlo de vuelta costó distinto.</p>
                    <div class="mfic__fila">
                        <select name="plan_id">
                            @foreach ($planes as $p)
                                <option value="{{ $p->id }}">{{ $p->nombre }}</option>
                            @endforeach
                        </select>
                        <select name="periodo">
                            <option value="mensual">Mes a mes</option>
                            <option value="anual">El año</option>
                        </select>
                        <x-mus.btn type="submit" variant="primary" icon="check">Reactivar</x-mus.btn>
                    </div>
                </form>
            @endif
        </div>
    </x-mus.panel>

    {{-- ══════════ Historial ══════════ --}}
    @if ($historial->count())
        <x-mus.panel title="Historial de ingreso" sub="Cada movimiento de esta cuenta" :pad="false" :reveal="true">
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr><th>Cuándo</th><th>Qué pasó</th><th>Quedó en</th><th>Cambio</th><th>Motivo</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($historial as $m)
                            <tr data-row>
                                <td>{{ Formato::enPalabras($m->ocurrio_en, 'D MMM YYYY') }}</td>
                                <td><b>{{ $m->etiqueta() }}</b></td>
                                <td>{{ $m->planDespues?->nombre ?? '—' }}</td>
                                <td class="{{ $m->suma() ? 'mfic__mas' : 'mfic__menos' }}">
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
        .mfic__cab{ display:flex; align-items:flex-start; justify-content:space-between;
                    gap:22px; flex-wrap:wrap; }
        .mfic__estado{ display:flex; align-items:center; gap:9px; flex-wrap:wrap; }
        .mfic__rubro{ font-size:11.6px; color:var(--muted); }

        /* minmax(0,…): un correo largo no puede ensanchar su columna y
           dejar a las demás sin espacio. */
        .mfic__datos{ display:grid; grid-template-columns:repeat(auto-fit, minmax(min(160px,100%), 1fr));
                      gap:12px 20px; margin:16px 0 0; }
        .mfic__datos div{ min-width:0; }
        .mfic__datos dt{ font-size:10.8px; font-weight:700; letter-spacing:.06em;
                         text-transform:uppercase; color:var(--muted); }
        .mfic__datos dd{ margin:2px 0 0; font-size:13px; color:var(--ink-2);
                         overflow:hidden; text-overflow:ellipsis; }
        .mfic__aporta{ flex:none; text-align:right; }
        .mfic__aporta b{ display:block; font-size:22px; color:var(--ink);
                         font-variant-numeric:tabular-nums; }
        .mfic__aporta span{ display:block; font-size:11.6px; color:var(--muted); }
        .mfic__aporta em{ display:block; margin-top:3px; font-style:normal; font-size:10.8px;
                          color:var(--a-500); }

        .mfic__cupo{ display:grid; grid-template-columns:repeat(auto-fit, minmax(min(190px,100%), 1fr));
                     gap:15px; }
        .mfic__cupo span{ display:block; font-size:12.4px; color:var(--ink-2);
                          font-variant-numeric:tabular-nums; }
        .mfic__cupo b{ display:block; font-size:12.6px; color:var(--ink); }
        .mfic__barra{ margin-top:7px; height:6px; border-radius:99px; background:var(--line-2);
                      overflow:hidden; }
        .mfic__barra i{ display:block; height:100%; background:var(--a-500); }
        .mfic__cupo .is-apretado .mfic__barra i{ background:var(--warn); }

        .mfic__form{ display:grid; grid-template-columns:repeat(auto-fit, minmax(min(200px,100%), 1fr));
                     gap:13px; }
        .mfic__form label{ display:block; min-width:0; }
        .mfic__form > label > span{ display:block; margin-bottom:5px; font-size:12px; color:var(--ink-2); }
        .mfic__form small{ color:var(--muted); font-size:10.6px; }
        .mfic__form input, .mfic__form select{ width:100%; padding:9px 12px; border-radius:9px;
                          border:1px solid var(--line); background:var(--card); color:var(--ink);
                          font-size:13px; font-family:inherit; }
        .mfic__form input:focus, .mfic__form select:focus{ outline:none; border-color:var(--a-500); }
        .mfic__ancho{ grid-column:1 / -1; }

        .mfic__acciones{ display:grid; gap:18px; }
        .mfic__acciones b{ display:block; font-size:13.4px; color:var(--ink); }
        .mfic__acciones p{ margin:3px 0 9px; max-width:66ch; font-size:12.2px; line-height:1.55;
                           color:var(--muted); }
        .mfic__fila{ display:flex; align-items:center; gap:9px; flex-wrap:wrap; }
        .mfic__fila input, .mfic__fila select{ flex:1 1 160px; min-width:0; padding:8px 12px;
                          border-radius:9px; border:1px solid var(--line); background:var(--card);
                          color:var(--ink); font-size:12.8px; font-family:inherit; }
        .mfic__fila span{ font-size:12.2px; color:var(--muted); }

        .mfic__mas{ color:var(--ok); font-variant-numeric:tabular-nums; }
        .mfic__menos{ color:var(--bad); font-variant-numeric:tabular-nums; }
    </style>
    @endpush
</x-mus.page>
