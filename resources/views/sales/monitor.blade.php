@php use App\Support\Formato; @endphp

<x-mus.page title="Ventas en vivo" subtitle="Lo que se está vendiendo hoy, al minuto" icon="receipt"
            :crumbs="['Ventas' => route('sales.index'), 'En vivo' => null]">
    <x-slot name="actions">
        <span class="mon-live" id="monLive" data-on="1">
            <i></i> <span id="monHora">En vivo</span>
        </span>
        <x-mus.btn type="button" icon="clock" id="monPausa">Pausar</x-mus.btn>
    </x-slot>

    {{-- ══════════ KPI del día ══════════ --}}
    <div class="mon-kpis">
        <div class="mon-k mon-k--main">
            <span>Vendido hoy</span>
            <b data-k="total">{{ Formato::moneda($resumen['total']) }}</b>
            <small><span data-k="ultima_hora">{{ $resumen['ultima_hora'] }}</span> ventas en la última hora</small>
        </div>
        <div class="mon-k"><span>Ventas</span><b data-k="ventas">{{ $resumen['ventas'] }}</b></div>
        <div class="mon-k"><span>Ticket promedio</span><b data-k="promedio">{{ Formato::moneda($resumen['promedio']) }}</b></div>
        <div class="mon-k"><span>Utilidad</span><b data-k="utilidad">{{ Formato::moneda($resumen['utilidad']) }}</b></div>
        <div class="mon-k {{ $resumen['anuladas'] ? 'is-bad' : '' }}" data-kbox="anuladas">
            <span>Anuladas</span><b data-k="anuladas">{{ $resumen['anuladas'] }}</b>
            <small data-k="anulado_total">{{ Formato::moneda($resumen['anulado_total']) }}</small>
        </div>
    </div>

    <div class="mon-grid">
        {{-- ══════════ Feed ══════════ --}}
        <x-mus.panel :pad="false" :reveal="false">
            <div class="mon-tabs">
                @foreach ($estados as $clave => $etiqueta)
                    <a href="{{ route('sales.monitor', ['estado' => $clave]) }}" class="{{ $estado === $clave ? 'is-on' : '' }}">{{ $etiqueta }}</a>
                @endforeach
                @if ($resumen['umbral'])
                    <span class="mon-umbral" title="3 veces el ticket promedio de los últimos 30 días">Alto valor ≥ {{ Formato::moneda($resumen['umbral']) }}</span>
                @endif
            </div>

            <div class="mt-wrap">
                <table class="mt mon-t">
                    <thead>
                        <tr>
                            <th>Venta</th>
                            <th>Hora</th>
                            <th>Cliente</th>
                            <th>Vendedor</th>
                            <th>Medio</th>
                            <th class="num">Total</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody id="monFilas">
                        @foreach ($filas as $f)
                            @include('sales._monitor_fila', ['f' => $f])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div id="monVacio" @if ($filas->count()) hidden @endif>
                <x-mus.empty icon="receipt" title="Todavía no hay ventas hoy" text="Esta pantalla se actualiza sola: la primera venta aparece aquí sin recargar." />
            </div>
        </x-mus.panel>

        {{-- ══════════ Medios ══════════ --}}
        <x-mus.panel title="Por medio de pago" sub="Lo que entró hoy (efectivo neto del vuelto)" :reveal="false">
            <div id="monMedios" class="mon-medios">
                @include('sales._monitor_medios', ['medios' => $resumen['medios'], 'total' => $resumen['total']])
            </div>
        </x-mus.panel>
    </div>

    <template id="monFila">
        <tr data-row data-id="">
            <td><a class="mt__id"></a><span class="mon-alto" hidden>Alto valor</span></td>
            <td class="mon-hora"></td>
            <td class="mon-cli"></td>
            <td class="mon-ven"></td>
            <td class="mon-med sub"></td>
            <td class="num"><b class="mon-tot"></b></td>
            <td class="mon-est"></td>
        </tr>
    </template>

    @push('styles')
    <style>
        .mon-live{ display:inline-flex; align-items:center; gap:7px; padding:6px 12px; border-radius:999px; font-size:12.5px; font-weight:600;
                   background:rgba(23,163,74,.1); color:#15803d; }
        .mon-live i{ width:8px; height:8px; border-radius:50%; background:#16a34a; box-shadow:0 0 0 0 rgba(22,163,74,.5); animation:monPulso 1.8s infinite; }
        .mon-live[data-on="0"]{ background:var(--paper); color:var(--muted); }
        .mon-live[data-on="0"] i{ background:var(--muted-2); animation:none; }
        @keyframes monPulso{ 0%{ box-shadow:0 0 0 0 rgba(22,163,74,.45); } 70%{ box-shadow:0 0 0 8px rgba(22,163,74,0); } 100%{ box-shadow:0 0 0 0 rgba(22,163,74,0); } }
        html.no-motion .mon-live i{ animation:none; }

        .mon-kpis{ display:grid; grid-template-columns:1.6fr repeat(4, minmax(0,1fr)); gap:12px; margin-bottom:16px; }
        .mon-k{ padding:15px 17px; border-radius:15px; background:var(--card); border:1px solid var(--line); min-width:0; }
        .mon-k span{ display:block; font-size:12px; color:var(--muted); }
        .mon-k b{ display:block; font-size:22px; letter-spacing:-.025em; font-variant-numeric:tabular-nums; color:var(--ink); margin-top:2px; transition:color .4s; }
        .mon-k small{ display:block; font-size:11.8px; color:var(--muted); margin-top:2px; }
        .mon-k small span{ display:inline; }
        .mon-k--main{ background:linear-gradient(135deg, var(--a-600), var(--a-500)); border-color:transparent; }
        .mon-k--main span, .mon-k--main small{ color:rgba(255,255,255,.8); }
        .mon-k--main b{ color:#fff; font-size:28px; }
        .mon-k.is-bad b{ color:var(--bad, #b91c1c); }
        .mon-k b.is-flash{ color:#16a34a; }
        .mon-k--main b.is-flash{ color:#d9f99d; }
        @media (max-width:1100px){ .mon-kpis{ grid-template-columns:repeat(2, minmax(0,1fr)); } .mon-k--main{ grid-column:1 / -1; } }

        .mon-grid{ display:grid; gap:16px; grid-template-columns:minmax(0,2.2fr) minmax(0,1fr); align-items:start; }
        @media (max-width:1100px){ .mon-grid{ grid-template-columns:minmax(0,1fr); } }

        .mon-tabs{ display:flex; gap:6px; align-items:center; flex-wrap:wrap; padding:12px 14px; border-bottom:1px solid var(--line-2); }
        .mon-tabs a{ padding:6px 12px; border-radius:999px; font-size:12.8px; font-weight:600; color:var(--ink-2); text-decoration:none; }
        .mon-tabs a:hover{ background:var(--paper); }
        .mon-tabs a.is-on{ background:var(--a-600); color:#fff; }
        .mon-umbral{ margin-left:auto; font-size:11.8px; color:var(--muted); }

        .mon-t td:first-child{ white-space:nowrap; }
        .mon-t tr.is-new td{ animation:monNueva 2.4s ease-out; }
        @keyframes monNueva{ from{ background:rgba(23,163,74,.14); } to{ background:transparent; } }
        .mon-t tr.is-void td{ color:var(--muted); }
        .mon-t tr.is-void .mon-tot{ text-decoration:line-through; }
        .mon-t tr.is-alto td{ background:rgba(245,165,36,.07); }
        .mon-alto{ display:inline-block; margin-left:6px; padding:1px 7px; border-radius:999px; font-size:10.5px; font-weight:700;
                   background:rgba(245,165,36,.18); color:#a16207; vertical-align:1px; white-space:nowrap; }
        .mon-motivo{ display:block; font-size:11.3px; color:var(--muted); margin-top:2px; max-width:220px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

        .mon-medios{ display:grid; gap:12px; }
        .mon-m__top{ display:flex; justify-content:space-between; font-size:13px; }
        .mon-m__top b{ font-variant-numeric:tabular-nums; }
        .mon-m__top span{ color:var(--ink-2); font-weight:600; }
        .mon-m__bar{ height:7px; border-radius:99px; background:var(--line-2); margin-top:5px; overflow:hidden; }
        .mon-m__bar i{ display:block; height:100%; border-radius:99px; background:var(--a-500); transition:width .5s; }
        .mon-m small{ font-size:11.5px; color:var(--muted); }
    </style>
    @endpush

    @push('scripts')
    <script>
    (function () {
        var URL_DATOS = @json(route('sales.monitor.datos', ['estado' => $estado]));
        var ESTADO = @json($estado);
        var filas = document.getElementById('monFilas'), plantilla = document.getElementById('monFila');
        var vacio = document.getElementById('monVacio'), live = document.getElementById('monLive');
        var hora = document.getElementById('monHora'), pausa = document.getElementById('monPausa');
        var pausado = false, temporizador = null;
        var pesos = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });

        function ultimoId() {
            var max = 0;
            filas.querySelectorAll('tr[data-id]').forEach(function (tr) { max = Math.max(max, +tr.dataset.id || 0); });
            return max;
        }

        function pintarFila(f) {
            var tr = plantilla.content.firstElementChild.cloneNode(true);
            tr.dataset.id = f.id;
            var a = tr.querySelector('.mt__id'); a.textContent = f.numero; a.href = f.url;
            tr.querySelector('.mon-alto').hidden = !f.alto;
            tr.querySelector('.mon-hora').textContent = f.hora;
            tr.querySelector('.mon-cli').textContent = f.cliente || 'Consumidor final';
            tr.querySelector('.mon-ven').textContent = f.vendedor;
            tr.querySelector('.mon-med').textContent = (f.medios || []).join(' + ') || '—';
            tr.querySelector('.mon-tot').textContent = pesos.format(f.total);
            marcarEstado(tr, f.anulada, f.motivo, f.anulo);
            if (f.alto) tr.classList.add('is-alto');
            return tr;
        }

        function marcarEstado(tr, anulada, motivo, anulo) {
            var celda = tr.querySelector('.mon-est');
            if (anulada) {
                tr.classList.add('is-void'); tr.classList.remove('is-alto');
                celda.innerHTML = '<span class="mbg mbg--bad"><i></i>Anulada</span>';
                if (motivo) {
                    var m = document.createElement('span'); m.className = 'mon-motivo';
                    m.textContent = motivo + (anulo ? ' · ' + anulo : ''); m.title = m.textContent;
                    celda.appendChild(m);
                }
            } else if (!celda.innerHTML.trim()) {
                celda.innerHTML = '<span class="mbg mbg--ok"><i></i>Pagada</span>';
            }
        }

        function kpi(clave, valor, dinero) {
            var el = document.querySelector('[data-k="' + clave + '"]');
            if (!el) return;
            var texto = dinero ? pesos.format(valor) : String(valor);
            if (el.textContent.trim() !== texto) {
                el.textContent = texto;
                el.classList.add('is-flash'); setTimeout(function () { el.classList.remove('is-flash'); }, 1200);
            }
        }

        function medios(lista, total) {
            var caja = document.getElementById('monMedios');
            if (!lista.length) { caja.innerHTML = '<p class="sub" style="margin:0">Aún no entra plata hoy.</p>'; return; }
            var max = Math.max.apply(null, lista.map(function (m) { return m.total; })) || 1;
            caja.innerHTML = lista.map(function (m) {
                var pct = total > 0 ? Math.round(m.total / total * 100) : 0;
                return '<div class="mon-m"><div class="mon-m__top"><span></span><b>' + pesos.format(m.total) + '</b></div>' +
                       '<div class="mon-m__bar"><i style="width:' + Math.round(m.total / max * 100) + '%"></i></div>' +
                       '<small>' + m.veces + ' ' + (m.veces === 1 ? 'pago' : 'pagos') + ' · ' + pct + ' %</small></div>';
            }).join('');
            caja.querySelectorAll('.mon-m__top span').forEach(function (s, i) { s.textContent = lista[i].nombre; });
        }

        function refrescar() {
            fetch(URL_DATOS + (URL_DATOS.indexOf('?') < 0 ? '?' : '&') + 'despues=' + ultimoId(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
            }).then(function (r) {
                if (r.status === 401) { window.location.reload(); throw new Error('sesión'); }
                return r.json();
            }).then(function (d) {
                var r = d.resumen;
                kpi('total', r.total, true); kpi('ventas', r.ventas); kpi('promedio', r.promedio, true);
                kpi('utilidad', r.utilidad, true); kpi('anuladas', r.anuladas); kpi('anulado_total', r.anulado_total, true);
                kpi('ultima_hora', r.ultima_hora);
                document.querySelector('[data-kbox="anuladas"]').classList.toggle('is-bad', r.anuladas > 0);
                medios(r.medios, r.total);

                d.nuevas.slice().reverse().forEach(function (f) {
                    var tr = pintarFila(f); tr.classList.add('is-new');
                    filas.insertBefore(tr, filas.firstChild);
                });
                if (ESTADO !== 'anuladas') {
                    d.anuladas.forEach(function (id) {
                        var tr = filas.querySelector('tr[data-id="' + id + '"]');
                        if (tr && !tr.classList.contains('is-void')) {
                            if (ESTADO === 'pagadas' || ESTADO === 'alto') { tr.remove(); return; }
                            tr.querySelector('.mon-est').innerHTML = ''; marcarEstado(tr, true, null, null);
                        }
                    });
                }
                vacio.hidden = !!filas.children.length;
                hora.textContent = 'En vivo · ' + d.hora.slice(0, 5);
            }).catch(function () {
                hora.textContent = 'Sin conexión · reintentando';
            });
        }

        function programar() { clearInterval(temporizador); temporizador = setInterval(function () { if (!pausado && !document.hidden) refrescar(); }, 15000); }

        pausa.addEventListener('click', function () {
            pausado = !pausado;
            live.dataset.on = pausado ? '0' : '1';
            hora.textContent = pausado ? 'En pausa' : 'En vivo';
            pausa.lastChild.textContent = pausado ? ' Reanudar' : ' Pausar';
            if (!pausado) refrescar();
        });
        document.addEventListener('visibilitychange', function () { if (!document.hidden && !pausado) refrescar(); });
        programar();
    })();
    </script>
    @endpush
</x-mus.page>
