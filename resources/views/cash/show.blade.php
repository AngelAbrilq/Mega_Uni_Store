@php
    $abierta = $cash->status === 'abierta';
    $dif     = (float) $cash->difference;
    $totalV  = $ventas->where('status', 'pagada')->sum('total');
@endphp

<x-mus.page title="Turno de caja #{{ $cash->id }}"
            subtitle="{{ $cash->user->name ?? '' }} · abierto el {{ $cash->opened_at?->locale('es')->isoFormat('D MMM YYYY, HH:mm') }}"
            icon="money"
            :crumbs="['Caja' => route('cash.index'), 'Turno' => null]">

    <x-slot name="actions">
        <x-mus.btn href="{{ route('cash.z', $cash) }}" icon="id" target="_blank">Cierre Z</x-mus.btn>
        @if ($abierta)
            @can('ventas.crear')
                <x-mus.btn href="{{ route('pos.index') }}" icon="plus">Vender</x-mus.btn>
            @endcan
            @can('caja.cerrar')
                <x-mus.btn variant="primary" icon="check" id="btnCerrar">Cerrar y arquear</x-mus.btn>
            @endcan
        @endif
    </x-slot>

    <div class="ckpi" data-reveal data-stagger>
        <div class="ckpi__c">
            <span>Base inicial</span>
            <b><i class="moneda">$</i>{{ number_format((float) $cash->opening_amount, 0, ',', '.') }}</b>
        </div>
        <div class="ckpi__c">
            <span>Vendido en el turno</span>
            <b><i class="moneda">$</i>{{ number_format((float) $totalV, 0, ',', '.') }}</b>
            <em>{{ $ventas->where('status', 'pagada')->count() }} ventas</em>
        </div>
        <div class="ckpi__c">
            <span>Efectivo esperado</span>
            <b><i class="moneda">$</i>{{ number_format($esperado, 0, ',', '.') }}</b>
            <em>base + cobros en efectivo</em>
        </div>
        @if ($abierta)
            <div class="ckpi__c ckpi__c--on">
                <span>Estado</span>
                <b>Abierto</b>
                <em>desde hace {{ $cash->opened_at?->locale('es')->diffForHumans(null, true) }}</em>
            </div>
        @else
            <div class="ckpi__c">
                <span>Contado</span>
                <b><i class="moneda">$</i>{{ number_format((float) $cash->counted_amount, 0, ',', '.') }}</b>
            </div>
            <div class="ckpi__c">
                <span>Diferencia</span>
                <b class="{{ abs($dif) < 0.01 ? 'ok' : ($dif > 0 ? '' : 'bad') }}">
                    {{ $dif > 0 ? '+' : '' }}${{ number_format($dif, 0, ',', '.') }}
                </b>
                <em>
                    {{ abs($dif) < 0.01 ? 'la caja cuadró' : ($dif > 0 ? 'sobrante' : 'faltante') }}
                </em>
            </div>
        @endif
    </div>

    <div class="cgrid">
        <x-mus.panel title="Ventas del turno" :pad="false" sub="{{ $ventas->count() }} registros">
            @if ($ventas->count())
                <div class="mt-wrap">
                    <table class="mt">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Cliente</th>
                                <th>Hora</th>
                                <th class="num">Total</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ventas as $v)
                                <tr data-row>
                                    <td>
                                        <a href="{{ route('sales.show', $v) }}" class="mt__id">{{ $v->number }}</a>
                                    </td>
                                    <td>{{ $v->customer?->full_name ?? 'Consumidor final' }}</td>
                                    <td>{{ $v->sold_at?->format('H:i') }}</td>
                                    <td class="num"><b><i class="moneda">$</i>{{ number_format((float) $v->total, 0, ',', '.') }}</b></td>
                                    <td>
                                        @if ($v->status === 'anulada')
                                            <x-mus.badge tone="bad" :dot="true">Anulada</x-mus.badge>
                                        @else
                                            <x-mus.badge tone="ok" :dot="true">Pagada</x-mus.badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-mus.empty icon="money" title="Sin ventas todavía"
                             text="Cuando registres la primera venta aparecerá aquí." />
            @endif
        </x-mus.panel>

        <div style="display:grid;gap:16px;grid-template-columns:minmax(0,1fr);align-content:start">
            <x-mus.panel title="Por medio de pago" sub="Lo que hay que comparar al arquear">
                @if ($porMedio->count())
                    <ul class="cmed">
                        @foreach ($porMedio as $m)
                            <li>
                                <span>
                                    <b>{{ $m->method_name }}</b>
                                    <i>{{ $m->veces }} {{ $m->veces === 1 ? 'movimiento' : 'movimientos' }}</i>
                                </span>
                                <em><i class="moneda">$</i>{{ number_format((float) $m->total, 0, ',', '.') }}</em>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="cvacio">Todavía no se ha cobrado nada en este turno.</p>
                @endif
            </x-mus.panel>

            <x-mus.panel title="Datos del turno">
                <dl class="mdl">
                    <div><dt>Abrió</dt><dd>{{ $cash->user->name ?? '—' }}</dd></div>
                    <div><dt>Apertura</dt><dd>{{ $cash->opened_at?->locale('es')->isoFormat('D MMM YYYY, HH:mm') }}</dd></div>
                    @if (! $abierta)
                        <div><dt>Cerró</dt><dd>{{ $cash->closer->name ?? '—' }}</dd></div>
                        <div><dt>Cierre</dt><dd>{{ $cash->closed_at?->locale('es')->isoFormat('D MMM YYYY, HH:mm') }}</dd></div>
                    @endif
                    @if ($cash->notes)
                        <div><dt>Notas</dt><dd style="white-space:pre-line">{{ $cash->notes }}</dd></div>
                    @endif
                </dl>

                <x-slot name="foot">
                    <x-mus.btn href="{{ route('cash.index') }}" icon="back" :block="true">
                        Volver a los turnos
                    </x-mus.btn>
                </x-slot>
            </x-mus.panel>
        </div>
    </div>

    @can('caja.cerrar')
        @if ($abierta)
            <div class="vmodal" id="modalCerrar" role="dialog" aria-modal="true">
                <form method="POST" action="{{ route('cash.cerrar', $cash) }}" class="vmodal__box">
                    @csrf
                    <h3>Arqueo de caja</h3>
                    <p>
                        Según el sistema debería haber
                        <b><i class="moneda">$</i>{{ number_format($esperado, 0, ',', '.') }}</b> en efectivo
                        (base de ${{ number_format((float) $cash->opening_amount, 0, ',', '.') }}
                        más los cobros en efectivo). Cuenta el cajón y escribe lo que hay de verdad.
                    </p>
                    <label>
                        Efectivo contado
                        <input type="number" name="counted_amount" step="1" min="0" required
                               placeholder="0" autocomplete="off">
                    </label>
                    <label style="margin-top:11px">
                        Observaciones
                        <textarea name="notes" rows="2" placeholder="Opcional"></textarea>
                    </label>
                    <div class="vmodal__foot">
                        <button type="button" class="mb mb--ghost" id="btnCancelarCerrar">Cancelar</button>
                        <button type="submit" class="mb mb--primary">Cerrar turno</button>
                    </div>
                </form>
            </div>
        @endif
    @endcan

    @push('styles')
    <style>
        .ckpi{ display:grid; gap:12px; margin-bottom:16px;
               grid-template-columns:repeat(auto-fit,minmax(174px,1fr)); }
        .ckpi__c{ padding:14px 16px; background:var(--card); border:1px solid var(--line);
                  border-radius:12px; }
        .ckpi__c--on{ border-color:rgba(150,112,60,.4); background:rgba(150,112,60,.05); }
        .ckpi__c > span{ display:block; font-size:11.1px; letter-spacing:.05em; text-transform:uppercase;
                         color:var(--muted-2); font-weight:600; }
        .ckpi__c b{ display:block; margin:6px 0 2px; font-size:21px; font-weight:700;
                    letter-spacing:-.03em; color:var(--ink); line-height:1.05;
                    font-variant-numeric:tabular-nums; }
        .ckpi__c b.ok{ color:var(--ok); }
        .ckpi__c b.bad{ color:var(--bad); }
        .ckpi__c em{ display:block; font-style:normal; font-size:11.4px; color:var(--muted); }

        .cgrid{ display:grid; gap:16px; grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);
                align-items:start; }
        @media (max-width:980px){ .cgrid{ grid-template-columns:minmax(0,1fr); } }

        .cmed{ list-style:none; margin:0; padding:0; }
        .cmed li{ display:flex; align-items:center; justify-content:space-between; gap:12px;
                  padding:10px 0; border-bottom:1px solid var(--line-2); }
        .cmed li:last-child{ border-bottom:0; }
        .cmed b{ display:block; font-size:12.9px; font-weight:600; color:var(--ink); }
        .cmed i{ display:block; font-style:normal; font-size:11.3px; color:var(--muted); }
        .cmed em{ font-style:normal; font-size:14px; font-weight:700; color:var(--ink);
                  font-variant-numeric:tabular-nums; }
        .cvacio{ margin:0; font-size:12.8px; color:var(--muted); }

        .vmodal{ position:fixed; inset:0; z-index:120; display:grid; place-items:center; padding:20px;
                 background:rgba(7,13,20,.62); backdrop-filter:blur(4px);
                 opacity:0; pointer-events:none; transition:opacity .26s var(--e-soft); }
        .vmodal.is-on{ opacity:1; pointer-events:auto; }
        .vmodal__box{ width:min(430px,100%); padding:22px; background:var(--card); border-radius:15px;
                      box-shadow:0 40px 90px -40px rgba(0,0,0,.6);
                      transform:translateY(14px) scale(.98); transition:transform .3s var(--e-back); }
        .vmodal.is-on .vmodal__box{ transform:none; }
        .vmodal__box h3{ margin:0 0 8px; font-size:16px; font-weight:600; color:var(--ink); }
        .vmodal__box p{ margin:0 0 14px; font-size:12.9px; color:var(--muted); line-height:1.6; }
        .vmodal__box p b{ color:var(--ink); }
        .vmodal__box label{ display:block; font-size:12.4px; font-weight:600; color:var(--ink-2); }
        .vmodal__box input, .vmodal__box textarea{
            width:100%; margin-top:6px; padding:10px 11px; border:1px solid var(--line);
            border-radius:9px; font:inherit; font-size:14px; color:var(--ink); }
        .vmodal__box input{ font-variant-numeric:tabular-nums; }
        .vmodal__foot{ display:flex; gap:9px; justify-content:flex-end; margin-top:16px; }
    </style>
    @endpush

    @push('scripts')
    <script>
    (function () {
        var m = document.getElementById('modalCerrar');
        var abrir = document.getElementById('btnCerrar');
        var cancelar = document.getElementById('btnCancelarCerrar');
        if (!m || !abrir) return;

        abrir.addEventListener('click', function () {
            m.classList.add('is-on');
            setTimeout(function () { var i = m.querySelector('input'); if (i) i.focus(); }, 240);
        });
        if (cancelar) cancelar.addEventListener('click', function () { m.classList.remove('is-on'); });
        m.addEventListener('click', function (e) { if (e.target === m) m.classList.remove('is-on'); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') m.classList.remove('is-on');
        });
    })();
    </script>
    @endpush
</x-mus.page>
