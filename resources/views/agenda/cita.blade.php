@php use App\Support\Formato; @endphp

<x-mus.page :title="$cita->titulo" :subtitle="'Cita ' . $cita->numero" icon="clock"
            :crumbs="['Agenda' => route('agenda.index', ['dia' => $cita->inicio->toDateString()]), $cita->numero => null]">

    <x-mus.panel :reveal="true">
        <div class="mcit__cab">
            <div>
                <x-mus.badge :tone="$cita->tono()" :dot="true">{{ $cita->etiquetaEstado() }}</x-mus.badge>

                <dl class="mcit__datos">
                    <div><dt>Cuándo</dt>
                         <dd>{{ Formato::enPalabras($cita->inicio, 'dddd D [de] MMMM') }}<br>
                             <b>{{ $cita->horas() }}</b> · {{ $cita->duracion() }} min</dd></div>
                    <div><dt>Quién atiende</dt>
                         <dd><span class="mcit__punto" style="background:{{ $cita->recurso->color }}"></span>
                             {{ $cita->recurso->nombre }}</dd></div>
                    <div><dt>Cliente</dt>
                         <dd>{{ $cita->quien() }}
                             @if ($cita->customer?->phone)
                                 <em>{{ $cita->customer->phone }}</em>
                             @endif</dd></div>
                    <div><dt>Servicio</dt><dd>{{ $cita->product->name ?? '—' }}</dd></div>
                    <div><dt>Agendó</dt><dd>{{ $cita->user->name ?? '—' }}</dd></div>
                    <div><dt>Venta</dt>
                         <dd>@if ($cita->sale)
                                 <a href="{{ route('sales.show', $cita->sale) }}">{{ $cita->sale->number }}</a>
                             @else — @endif</dd></div>
                </dl>

                @if ($cita->notas)
                    <p class="mcit__notas">{{ $cita->notas }}</p>
                @endif
            </div>

            <div class="mcit__precio">
                <b>{{ Formato::moneda($cita->precio) }}</b>
                <span>{{ $cita->sale ? 'cobrado' : 'por cobrar' }}</span>
            </div>
        </div>
    </x-mus.panel>

    {{-- ══════════ Qué pasó ══════════ --}}
    @can('citas.editar')
        <x-mus.panel title="¿Qué pasó?" sub="Cerrar la cita es lo que hace que el informe sirva" :reveal="true">
            <div class="mcit__acciones">
                @foreach (['confirmada' => 'Confirmó que viene', 'atendida' => 'Se atendió',
                           'no_llego' => 'No llegó', 'cancelada' => 'Canceló'] as $estado => $texto)
                    @if ($cita->estado !== $estado)
                        <form method="POST" action="{{ route('agenda.estado', $cita) }}">
                            @csrf
                            <input type="hidden" name="estado" value="{{ $estado }}">
                            <x-mus.btn type="submit"
                                       :variant="$estado === 'atendida' ? 'primary' : ($estado === 'no_llego' ? 'danger' : 'ghost')">
                                {{ $texto }}
                            </x-mus.btn>
                        </form>
                    @endif
                @endforeach
            </div>

            <p class="mcit__pista">
                «No llegó» y «Canceló» se guardan aparte a propósito. Una cancelación avisada
                deja el cupo libre para otro; un plantón se pierde entero. Contarlos juntos te
                quitaría el único dato que sirve para decidir a quién le pides abono la próxima vez.
            </p>
        </x-mus.panel>

        {{-- ══════════ Cobrar ══════════ --}}
        @if ($cita->sePuedeCobrar())
            <x-mus.panel title="Cobrar" sub="Se genera la venta con este servicio" :reveal="true">
                <form method="POST" action="{{ route('agenda.cobrar', $cita) }}" class="mcit__cobrar">
                    @csrf
                    <p>Se va a crear una venta por {{ Formato::moneda($cita->precio) }} a nombre de
                       {{ $cita->quien() }}. Si necesitas cobrar con dos medios o hacer un descuento,
                       usa el punto de venta.</p>

                    {{-- Con qué se pagó. No es opcional: una venta sin pago
                         no cuadra la caja del día, y descuadrar la caja por
                         un clic de más es exactamente lo que nadie perdona. --}}
                    @if ($medios->isEmpty())
                        <p class="mcit__mal">
                            Este negocio no tiene medios de pago activos. Crea al menos uno en
                            <a href="{{ route('payment_methods.index') }}">Medios de pago</a> antes de cobrar.
                        </p>
                    @else
                        <div class="mcit__pago">
                            <label>
                                <span>Con qué paga</span>
                                <select name="payment_method_id" required>
                                    @foreach ($medios as $m)
                                        <option value="{{ $m->id }}" @selected($loop->first)>{{ $m->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <x-mus.btn type="submit" variant="primary" icon="money">Generar la venta</x-mus.btn>
                        </div>
                    @endif

                    @error('payment_method_id')<i class="mcit__mal">{{ $message }}</i>@enderror
                </form>
            </x-mus.panel>
        @endif

        {{-- ══════════ Mover ══════════ --}}
        @if ($cita->estaViva())
            <x-mus.panel title="Mover" sub="Otra hora, u otra persona" :reveal="true">
                <form method="POST" action="{{ route('agenda.mover', $cita) }}" class="mcit__mover">
                    @csrf
                    <label>
                        <span>Nueva hora</span>
                        <input type="datetime-local" name="inicio" required
                               value="{{ $cita->inicio->format('Y-m-d\TH:i') }}">
                        @error('inicio')<i>{{ $message }}</i>@enderror
                    </label>
                    <label>
                        <span>Dura</span>
                        <input type="number" name="minutos" min="5" max="600" step="5" value="{{ $cita->duracion() }}">
                    </label>
                    <x-mus.btn type="submit" icon="next">Mover</x-mus.btn>
                </form>
            </x-mus.panel>
        @endif
    @endcan

    @push('styles')
    <style>
        .mcit__cab{ display:flex; align-items:flex-start; justify-content:space-between; gap:22px; flex-wrap:wrap; }
        /* minmax(0,…): un nombre de cliente largo no puede ensanchar su
           columna y dejar a las otras sin sitio. */
        .mcit__datos{ display:grid; grid-template-columns:repeat(auto-fit, minmax(min(170px,100%), 1fr));
                      gap:14px 22px; margin:16px 0 0; }
        .mcit__datos div{ min-width:0; }
        .mcit__datos dt{ font-size:10.8px; font-weight:700; letter-spacing:.06em;
                         text-transform:uppercase; color:var(--muted); }
        .mcit__datos dd{ margin:3px 0 0; font-size:13px; color:var(--ink-2); line-height:1.5; }
        .mcit__datos dd em{ display:block; font-style:normal; font-size:11.6px; color:var(--muted); }
        .mcit__punto{ display:inline-block; width:8px; height:8px; border-radius:99px;
                      margin-right:5px; vertical-align:middle; }
        .mcit__notas{ margin:16px 0 0; max-width:70ch; padding:11px 14px; border-radius:10px;
                      background:var(--paper); font-size:12.6px; line-height:1.6; color:var(--ink-2); }
        .mcit__precio{ flex:none; text-align:right; }
        .mcit__precio b{ display:block; font-size:23px; color:var(--ink); font-variant-numeric:tabular-nums; }
        .mcit__precio span{ display:block; font-size:11.6px; color:var(--muted); }

        .mcit__acciones{ display:flex; gap:9px; flex-wrap:wrap; }
        .mcit__pista{ margin:16px 0 0; max-width:74ch; font-size:11.8px; line-height:1.6; color:var(--muted); }
        .mcit__cobrar p{ margin:0 0 12px; max-width:70ch; font-size:12.8px; line-height:1.6; color:var(--ink-2); }
        .mcit__pago{ display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
        .mcit__pago label{ display:block; min-width:0; }
        .mcit__pago span{ display:block; margin-bottom:5px; font-size:12px; color:var(--ink-2); }
        .mcit__pago select{ padding:9px 12px; border-radius:9px; border:1px solid var(--line);
                            background:var(--card); color:var(--ink); font-size:13px; font-family:inherit; }
        .mcit__pago select:focus{ outline:none; border-color:var(--a-500); }
        .mcit__mal{ display:block; margin:0; font-style:normal; font-size:12.4px;
                    line-height:1.6; color:var(--bad); }
        .mcit__mal a{ color:inherit; }
        .mcit__mover{ display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
        .mcit__mover label{ display:block; min-width:0; }
        .mcit__mover span{ display:block; margin-bottom:5px; font-size:12px; color:var(--ink-2); }
        .mcit__mover input{ padding:9px 12px; border-radius:9px; border:1px solid var(--line);
                            background:var(--card); color:var(--ink); font-size:13px; font-family:inherit; }
        .mcit__mover i{ display:block; margin-top:4px; font-style:normal; font-size:11.4px; color:var(--bad); }
    </style>
    @endpush
</x-mus.page>
