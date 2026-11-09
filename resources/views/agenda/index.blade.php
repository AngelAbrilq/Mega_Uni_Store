@php
    use App\Support\Formato;

    $ayer   = $dia->copy()->subDay()->toDateString();
    $manana = $dia->copy()->addDay()->toDateString();
    $horas  = range($horaDesde, $horaHasta - 1);

    /* Alto de una hora, en píxeles. Todo lo demás se calcula de aquí:
       una cita de 45 min mide tres cuartos de esto. */
    $altoHora = 64;

    /* Dónde cae una hora dentro de la rejilla. */
    $y = fn ($fecha) => (($fecha->hour + $fecha->minute / 60) - $horaDesde) * $altoHora;
@endphp

<x-mus.page title="Agenda" :subtitle="Formato::enPalabras($dia, 'dddd D [de] MMMM [de] YYYY')" icon="clock">
    <x-slot name="actions">
        @can('citas.crear')
            @if ($recursos->count())
                <x-mus.btn type="button" variant="primary" icon="plus" data-nueva-cita>Nueva cita</x-mus.btn>
            @endif
        @endcan
        @can('agenda.recursos')
            <x-mus.btn href="{{ route('recursos.index') }}" icon="users">Quién atiende</x-mus.btn>
        @endcan
    </x-slot>

    {{-- ══════════ Navegación del día ══════════ --}}
    <div class="mag__nav">
        <a href="{{ route('agenda.index', ['dia' => $ayer]) }}" class="mag__flecha" aria-label="Día anterior">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                 stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        </a>

        <form method="GET" action="{{ route('agenda.index') }}" class="mag__fecha">
            <input type="date" name="dia" value="{{ $dia->toDateString() }}" onchange="this.form.submit()">
        </form>

        <a href="{{ route('agenda.index', ['dia' => $manana]) }}" class="mag__flecha" aria-label="Día siguiente">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                 stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>

        @unless ($dia->isToday())
            <a href="{{ route('agenda.index') }}" class="mag__hoy">Hoy</a>
        @endunless

        <div class="mag__cifras">
            <span><b>{{ $resumen['total'] }}</b> citas</span>
            @if ($resumen['pendientes'])
                <span class="is-pend"><b>{{ $resumen['pendientes'] }}</b> sin confirmar</span>
            @endif
            @if ($resumen['no_llego'])
                <span class="is-mal"><b>{{ $resumen['no_llego'] }}</b> no llegaron</span>
            @endif
            <span class="is-plata" title="Lo que vale el día si todos llegan">
                {{ Formato::moneda($resumen['en_juego']) }} en juego
            </span>
        </div>
    </div>

    @if ($recursos->isEmpty())
        <x-mus.panel :reveal="true">
            <x-mus.empty icon="users" title="Todavía no hay quién atienda"
                         text="La agenda necesita saber quién atiende: un estilista, una silla, una cabina, un consultorio. Con eso ya se puede agendar.">
                @can('agenda.recursos')
                    <x-slot name="action">
                        <x-mus.btn href="{{ route('recursos.create') }}" variant="primary" icon="plus">
                            Agregar el primero
                        </x-mus.btn>
                    </x-slot>
                @endcan
            </x-mus.empty>
        </x-mus.panel>
    @else
        {{-- ══════════ La rejilla ══════════ --}}
        <div class="mag" style="--alto-hora:{{ $altoHora }}px; --cols:{{ $recursos->count() }}">
            {{-- Cabecera: quién atiende --}}
            <div class="mag__cab">
                <div class="mag__esq"></div>
                @foreach ($recursos as $r)
                    <div class="mag__quien">
                        <span class="mag__punto" style="background:{{ $r->color }}"></span>
                        <b>{{ $r->nombreCorto() }}</b>
                        <em>{{ $ocupacion[$r->id] }}% ocupado</em>
                        <span class="mag__ocupa"><i style="width:{{ $ocupacion[$r->id] }}%; background:{{ $r->color }}"></i></span>
                    </div>
                @endforeach
            </div>

            {{-- Cuerpo --}}
            <div class="mag__cuerpo">
                <div class="mag__horas">
                    @foreach ($horas as $h)
                        <div class="mag__hora"><span>{{ \Illuminate\Support\Carbon::today()->setTime($h, 0)->format('g a') }}</span></div>
                    @endforeach
                </div>

                @foreach ($recursos as $r)
                    @php $suyas = $citas->where('recurso_id', $r->id); @endphp

                    <div class="mag__col" data-recurso="{{ $r->id }}">
                        {{-- Las franjas en que NO trabaja, en gris. Es lo que
                             evita que alguien agende a las 7 a. m. sin darse
                             cuenta de que abre a las 9. --}}
                        @php $franjas = $r->franjasDe($dia); @endphp
                        @if ($franjas->isEmpty())
                            <div class="mag__cerrado" style="top:0; height:100%">
                                <span>No trabaja</span>
                            </div>
                        @else
                            @foreach ($franjas as $i => $f)
                                @php
                                    $ini = $f->inicioEn($dia); $fn = $f->finEn($dia);
                                    $antes = $y($ini);
                                @endphp
                                @if ($i === 0 && $antes > 0)
                                    <div class="mag__cerrado" style="top:0; height:{{ $antes }}px"></div>
                                @endif
                                @if ($i === $franjas->count() - 1)
                                    @php $sobra = (($horaHasta - $horaDesde) * $altoHora) - $y($fn); @endphp
                                    @if ($sobra > 0)
                                        <div class="mag__cerrado" style="top:{{ $y($fn) }}px; height:{{ $sobra }}px"></div>
                                    @endif
                                @endif
                            @endforeach
                        @endif

                        {{-- Las líneas de cada hora --}}
                        @foreach ($horas as $h)
                            <div class="mag__linea" style="top:{{ ($h - $horaDesde) * $altoHora }}px"></div>
                        @endforeach

                        {{-- Las citas --}}
                        @foreach ($suyas as $c)
                            @php
                                $arriba = $y($c->inicio);
                                $alto   = max(22, ($c->duracion() / 60) * $altoHora);
                                $muerta = ! $c->estaViva();
                            @endphp
                            <a href="{{ route('agenda.cita', $c) }}"
                               class="mag__cita is-{{ $c->estado }} {{ $muerta ? 'is-muerta' : '' }}"
                               style="top:{{ $arriba }}px; height:{{ $alto }}px;
                                      --c:{{ $r->color }}; --ct:{{ $r->colorTexto() }}"
                               title="{{ $c->titulo }} · {{ $c->quien() }} · {{ $c->horas() }}">
                                <b>{{ $c->titulo }}</b>
                                <em>{{ $c->quien() }}</em>
                                <i>{{ $c->inicio->format('g:i a') }}</i>
                            </a>
                        @endforeach
                    </div>
                @endforeach

                {{-- La línea de «ahora». Solo si el día es hoy. --}}
                @if ($dia->isToday() && now()->hour >= $horaDesde && now()->hour < $horaHasta)
                    <div class="mag__ahora" style="top:{{ $y(now()) }}px">
                        <span>{{ now()->format('g:i a') }}</span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ══════════ Nueva cita ══════════ --}}
    @can('citas.crear')
        @if ($recursos->count())
            <x-mus.panel title="Agendar" sub="Escoge quién, cuándo y qué" :reveal="true" id="musNuevaCita">
                <form method="POST" action="{{ route('agenda.store') }}" novalidate>
                    @csrf
                    <div class="magf">
                        <label>
                            <span>Quién atiende</span>
                            <select name="recurso_id" required>
                                @foreach ($recursos as $r)
                                    <option value="{{ $r->id }}" @selected(old('recurso_id') == $r->id)>{{ $r->nombre }}</option>
                                @endforeach
                            </select>
                            @error('recurso_id')<i>{{ $message }}</i>@enderror
                        </label>

                        <label>
                            <span>Cuándo</span>
                            <input type="datetime-local" name="inicio" required
                                   value="{{ old('inicio', $dia->copy()->setTime(max(9, $horaDesde), 0)->format('Y-m-d\TH:i')) }}">
                            @error('inicio')<i>{{ $message }}</i>@enderror
                        </label>

                        <label>
                            <span>Servicio <small>define duración y precio</small></span>
                            <select name="product_id" data-servicio>
                                <option value="">Sin servicio (cita suelta)</option>
                                @foreach ($servicios as $s)
                                    <option value="{{ $s->id }}"
                                            data-min="{{ $s->duracion_minutos }}"
                                            data-precio="{{ (float) $s->price }}"
                                            @selected(old('product_id') == $s->id)>
                                        {{ $s->name }} · {{ $s->duracion_minutos }} min
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <span>Cliente</span>
                            <select name="customer_id">
                                <option value="">Sin cliente</option>
                                @foreach ($clientes as $cl)
                                    <option value="{{ $cl->id }}" @selected(old('customer_id') == $cl->id)>
                                        {{ trim($cl->first_name . ' ' . $cl->last_name) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <span>Dura <small>minutos</small></span>
                            <input type="number" name="minutos" min="5" max="600" step="5"
                                   value="{{ old('minutos', 30) }}" data-min-cita>
                            @error('minutos')<i>{{ $message }}</i>@enderror
                        </label>

                        <label>
                            <span>Precio</span>
                            <input type="number" name="precio" min="0" step="1"
                                   value="{{ old('precio', 0) }}" data-precio-cita>
                        </label>

                        <label class="magf__ancho">
                            <span>Título <small>si no pones, va el del servicio</small></span>
                            <input type="text" name="titulo" maxlength="150" value="{{ old('titulo') }}"
                                   placeholder="Corte y barba · Juan">
                        </label>

                        <label class="magf__ancho">
                            <span>Notas</span>
                            <input type="text" name="notas" maxlength="500" value="{{ old('notas') }}"
                                   placeholder="Llega en moto, viene con el hijo…">
                        </label>
                    </div>

                    <x-slot name="foot">
                        <x-mus.btn type="submit" variant="primary" icon="save">Agendar</x-mus.btn>
                    </x-slot>
                </form>
            </x-mus.panel>
        @endif
    @endcan

    @push('scripts')
    <script>
        /* Escoger el servicio llena la duración y el precio. Se puede pisar
           a mano después: en la vida real se negocia. */
        (function () {
            var sel = document.querySelector('[data-servicio]');
            var min = document.querySelector('[data-min-cita]');
            var pre = document.querySelector('[data-precio-cita]');
            if (!sel || !min || !pre) return;

            sel.addEventListener('change', function () {
                var op = sel.options[sel.selectedIndex];
                if (!op || !op.dataset.min) return;
                min.value = op.dataset.min;
                pre.value = op.dataset.precio;
            });

            var abrir = document.querySelector('[data-nueva-cita]');
            var caja  = document.getElementById('musNuevaCita');
            if (abrir && caja) {
                abrir.addEventListener('click', function () {
                    caja.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    var primero = caja.querySelector('select, input');
                    if (primero) setTimeout(function () { primero.focus(); }, 320);
                });
            }
        })();
    </script>
    @endpush

    @push('styles')
    <style>
        /* ── Navegación ── */
        .mag__nav{ display:flex; align-items:center; gap:9px; flex-wrap:wrap; margin-bottom:14px; }
        .mag__flecha{ display:grid; place-items:center; width:32px; height:32px; flex:none;
                      border:1px solid var(--line); border-radius:9px; background:var(--card);
                      color:var(--ink-2); text-decoration:none; transition:border-color .16s; }
        .mag__flecha:hover{ border-color:var(--muted-2); }
        .mag__flecha svg{ width:15px; height:15px; }
        .mag__fecha input{ padding:7px 11px; border:1px solid var(--line); border-radius:9px;
                           background:var(--card); color:var(--ink); font-size:13px; font-family:inherit; }
        .mag__hoy{ padding:7px 13px; border-radius:9px; background:var(--a-500); color:#fff;
                   font-size:12.4px; text-decoration:none; }
        .mag__cifras{ display:flex; align-items:center; gap:14px; flex-wrap:wrap;
                      margin-left:auto; font-size:12.2px; color:var(--muted); }
        .mag__cifras b{ color:var(--ink); font-variant-numeric:tabular-nums; }
        .mag__cifras .is-pend b{ color:var(--warn); }
        .mag__cifras .is-mal b{ color:var(--bad); }
        .mag__cifras .is-plata{ padding:3px 10px; border-radius:99px; background:var(--line-2);
                                color:var(--ink-2); font-variant-numeric:tabular-nums; }

        /* ── La rejilla ──
           minmax(0,1fr) por columna: con `1fr` a secas, una cita de título
           largo ensancha su columna y las demás se encogen — el calendario
           dejaría de estar a escala, que es lo único que tiene que hacer. */
        .mag{ border:1px solid var(--line); border-radius:13px; overflow:hidden;
              background:var(--card); }
        .mag__cab{ display:grid; grid-template-columns:56px repeat(var(--cols), minmax(0, 1fr));
                   border-bottom:1px solid var(--line); background:var(--paper); }
        .mag__esq{ border-right:1px solid var(--line-2); }
        .mag__quien{ padding:10px 12px; border-right:1px solid var(--line-2); min-width:0; }
        .mag__quien:last-child{ border-right:0; }
        .mag__punto{ display:inline-block; width:8px; height:8px; border-radius:99px;
                     margin-right:6px; vertical-align:middle; }
        .mag__quien b{ font-size:12.8px; color:var(--ink); }
        .mag__quien em{ display:block; margin-top:2px; font-style:normal; font-size:10.8px;
                        color:var(--muted); }
        .mag__ocupa{ display:block; margin-top:5px; height:3px; border-radius:99px;
                     background:var(--line-2); overflow:hidden; }
        .mag__ocupa i{ display:block; height:100%; }

        .mag__cuerpo{ position:relative; display:grid;
                      grid-template-columns:56px repeat(var(--cols), minmax(0, 1fr));
                      overflow-x:auto; }
        .mag__horas{ border-right:1px solid var(--line-2); }
        .mag__hora{ height:var(--alto-hora); position:relative; }
        .mag__hora span{ position:absolute; top:-7px; right:8px; font-size:10.4px;
                         color:var(--muted); background:var(--card); padding:0 3px; }
        /* La primera etiqueta no puede subir: se la comería el borde de
           arriba de la rejilla y la agenda abriría con la hora cortada. */
        .mag__hora:first-child span{ top:3px; }

        .mag__col{ position:relative; border-right:1px solid var(--line-2); min-width:0; }
        .mag__col:last-child{ border-right:0; }
        .mag__linea{ position:absolute; left:0; right:0; height:1px; background:var(--line-2); }

        /* Fuera de horario: rayado, no gris plano. El gris plano se lee como
           «vacío» y esto no está vacío: está cerrado. */
        .mag__cerrado{ position:absolute; left:0; right:0; z-index:1;
                       background:repeating-linear-gradient(45deg,
                            var(--line-2) 0 6px, transparent 6px 12px); opacity:.55; }
        .mag__cerrado span{ position:absolute; top:8px; left:50%; transform:translateX(-50%);
                            font-size:10.6px; color:var(--muted); white-space:nowrap; }

        .mag__cita{ position:absolute; left:4px; right:4px; z-index:2; overflow:hidden;
                    padding:5px 8px; border-radius:8px; text-decoration:none;
                    background:var(--c); color:var(--ct);
                    border-left:3px solid rgba(0,0,0,.22);
                    transition:filter .16s, transform .16s; }
        .mag__cita:hover{ filter:brightness(1.06); transform:translateX(1px); z-index:3; }
        .mag__cita b{ display:block; font-size:11.8px; font-weight:650; line-height:1.25;
                      overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .mag__cita em{ display:block; font-style:normal; font-size:10.8px; opacity:.86;
                       overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .mag__cita i{ display:block; font-style:normal; font-size:10.2px; opacity:.72; }

        /* Sin confirmar: rayas. Se ve distinto sin depender del color del
           recurso, que ya está ocupado diciendo quién atiende.

           Las rayas van como `background-image` ENCIMA del color, no en vez
           de él. Puestas en el atajo `background` reemplazan el color, y
           entonces el blanco translúcido se mezcla con el papel de la tarjeta
           en lugar de con el color del recurso: quedan bandas casi blancas y
           el nombre del cliente deja de leerse — justo en las citas que hay
           que leer, que son las que falta confirmar.

           Las bandas alternan blanco y negro translúcidos a propósito: el
           color del recurso lo escoge el dueño y puede ser claro u oscuro.
           Con rayas solo blancas, un recurso de color claro no mostraría
           ninguna diferencia entre confirmada y sin confirmar. */
        .mag__cita.is-pendiente{
            background-color:var(--c);
            background-image:repeating-linear-gradient(135deg,
                 rgba(255,255,255,.16) 0 6px, rgba(0,0,0,.09) 6px 13px);
            border-left-style:dashed;
        }
        .mag__cita.is-muerta{ opacity:.42; filter:grayscale(.7); }
        .mag__cita.is-muerta b{ text-decoration:line-through; }

        .mag__ahora{ position:absolute; left:56px; right:0; height:2px; z-index:4;
                     background:var(--bad); pointer-events:none; }
        .mag__ahora span{ position:absolute; top:-8px; left:4px; padding:1px 6px;
                          border-radius:99px; background:var(--bad); color:#fff;
                          font-size:9.6px; font-variant-numeric:tabular-nums; }

        /* ── Formulario ── */
        .magf{ display:grid; grid-template-columns:repeat(auto-fit, minmax(min(200px,100%), 1fr)); gap:13px; }
        /* Con `>`: si mañana entra aquí un componente que traiga su propio
           <label> —un interruptor, un selector— estas reglas no se lo pisan. */
        .magf > label{ display:block; min-width:0; }
        .magf > label > span{ display:block; margin-bottom:5px; font-size:12px; color:var(--ink-2); }
        .magf > label small{ color:var(--muted); font-size:10.6px; }
        .magf > label > input, .magf > label > select{ width:100%; padding:9px 12px; border-radius:9px;
                   border:1px solid var(--line); background:var(--card); color:var(--ink);
                   font-size:13px; font-family:inherit; }
        .magf > label > input:focus, .magf > label > select:focus{ outline:none; border-color:var(--a-500); }
        .magf > label > i{ display:block; margin-top:4px; font-style:normal; font-size:11.4px; color:var(--bad); }
        .magf__ancho{ grid-column:1 / -1; }

        @media (max-width:760px){
            .mag__cuerpo, .mag__cab{ grid-template-columns:44px repeat(var(--cols), minmax(120px, 1fr)); }
            .mag__cifras{ width:100%; margin-left:0; }
        }
    </style>
    @endpush
</x-mus.page>
