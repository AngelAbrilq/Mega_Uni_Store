@php
    $tonos = ['borrador' => 'warn', 'recibida' => 'ok', 'anulada' => 'bad'];
@endphp

<x-mus.page title="Compra {{ $purchase->number }}"
            subtitle="{{ $purchase->supplier->name ?? '' }} · {{ $purchase->ordered_at?->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}"
            icon="truck"
            :crumbs="['Compras' => route('purchases.index'), 'Detalle' => null]">

    <x-slot name="actions">
        @can('compras.editar')
            @if ($purchase->editable)
                <x-mus.btn href="{{ route('purchases.edit', $purchase) }}" icon="pencil">Editar</x-mus.btn>
            @endif
        @endcan
        @can('compras.recibir')
            @if ($purchase->status === 'borrador')
                <x-mus.btn variant="primary" icon="check" id="btnRecibir">Dar entrada a la mercancía</x-mus.btn>
            @endif
        @endcan
        @can('compras.eliminar')
            @if ($purchase->status !== 'anulada')
                <x-mus.btn variant="danger" icon="alert" id="btnAnularC">Anular</x-mus.btn>
            @endif
        @endcan
    </x-slot>

    @if ($purchase->status === 'borrador')
        <div class="cav cav--warn" data-reveal>
            <span><x-mus.icon name="clock" :w="16" stroke-width="2.2" /></span>
            <div>
                <b>Compra en borrador</b>
                <p>El inventario todavía no se ha movido. Cuando llegue la mercancía,
                   dale entrada y los {{ $purchase->items->count() }} productos subirán de stock
                   con su movimiento de kardex.</p>
            </div>
        </div>
    @elseif ($purchase->status === 'recibida')
        <div class="cav cav--ok" data-reveal>
            <span><x-mus.icon name="check" :w="16" stroke-width="2.6" /></span>
            <div>
                <b>Mercancía recibida</b>
                <p>{{ $purchase->receiver->name ?? 'Alguien' }} le dio entrada
                   el {{ $purchase->received_at?->locale('es')->isoFormat('D MMM YYYY, HH:mm') }}.
                   El inventario ya está actualizado.</p>
            </div>
        </div>
    @else
        <div class="cav cav--bad" data-reveal>
            <span><x-mus.icon name="alert" :w="16" stroke-width="2.2" /></span>
            <div>
                <b>Compra anulada</b>
                <p>Si ya se había recibido, la mercancía salió del inventario con su movimiento de kardex.</p>
            </div>
        </div>
    @endif

    <div class="vgrid">
        <x-mus.panel title="Productos" :pad="false" sub="{{ $purchase->items->count() }} renglones">
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="num">Cantidad</th>
                            <th class="num">Costo</th>
                            <th class="num">IVA</th>
                            <th class="num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchase->items as $item)
                            <tr>
                                <td>
                                    @if ($item->product)
                                        <a href="{{ route('inventory.kardex', $item->product) }}"><b>{{ $item->name }}</b></a>
                                        <span class="sub">
                                            {{ $item->product->sku }} · existencias hoy: {{ $item->product->stock_texto }}
                                        </span>
                                    @else
                                        <b>{{ $item->name }}</b>
                                    @endif
                                </td>
                                <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="num"><i class="moneda">$</i>{{ number_format((float) $item->unit_cost, 0, ',', '.') }}</td>
                                <td class="num">
                                    @if ((float) $item->tax_amount > 0)
                                        ${{ number_format((float) $item->tax_amount, 0, ',', '.') }}
                                        <span class="sub">{{ rtrim(rtrim(number_format((float) $item->tax_rate, 2, ',', '.'), '0'), ',') }}%</span>
                                    @else — @endif
                                </td>
                                <td class="num"><b><i class="moneda">$</i>{{ number_format((float) $item->total, 0, ',', '.') }}</b></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="ctot">
                <div><span>Subtotal</span><b><i class="moneda">$</i>{{ number_format((float) $purchase->subtotal, 0, ',', '.') }}</b></div>
                <div><span>Impuestos</span><b><i class="moneda">$</i>{{ number_format((float) $purchase->tax_total, 0, ',', '.') }}</b></div>
                <div class="ctot__big"><span>Total</span><b><i class="moneda">$</i>{{ number_format((float) $purchase->total, 0, ',', '.') }}</b></div>
            </div>
        </x-mus.panel>

        <x-mus.panel title="Datos de la compra">
            <dl class="mdl">
                <div>
                    <dt>Proveedor</dt>
                    <dd>
                        @if ($purchase->supplier)
                            <a href="{{ route('suppliers.show', $purchase->supplier) }}">{{ $purchase->supplier->name }}</a>
                        @else — @endif
                    </dd>
                </div>
                <div><dt>Factura</dt><dd>{{ $purchase->invoice_number ?: '—' }}</dd></div>
                <div>
                    <dt>Estado</dt>
                    <dd>
                        <x-mus.badge :tone="$tonos[$purchase->status] ?? 'soft'" :dot="true">
                            {{ $purchase->estado_label }}
                        </x-mus.badge>
                    </dd>
                </div>
                <div><dt>Registró</dt><dd>{{ $purchase->user->name ?? '—' }}</dd></div>
                <div><dt>Fecha del pedido</dt><dd>{{ $purchase->ordered_at?->locale('es')->isoFormat('D MMM YYYY') }}</dd></div>
                @if ($purchase->received_at)
                    <div><dt>Recibió</dt><dd>{{ $purchase->receiver->name ?? '—' }}</dd></div>
                    <div><dt>Entrada</dt><dd>{{ $purchase->received_at->locale('es')->isoFormat('D MMM YYYY, HH:mm') }}</dd></div>
                @endif
                @if ($purchase->notes)
                    <div><dt>Observaciones</dt><dd style="white-space:pre-line">{{ $purchase->notes }}</dd></div>
                @endif
            </dl>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('purchases.index') }}" icon="back" :block="true">
                    Volver al listado
                </x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </div>

    @can('compras.recibir')
        @if ($purchase->status === 'borrador')
            <div class="vmodal" id="modalRecibir" role="dialog" aria-modal="true">
                <form method="POST" action="{{ route('purchases.recibir', $purchase) }}" class="vmodal__box">
                    @csrf
                    <h3>Dar entrada a la mercancía</h3>
                    <p>
                        Se van a sumar al inventario los {{ $purchase->items->count() }} productos
                        de esta compra, cada uno con su movimiento de kardex. Los que tengan
                        marcada la casilla también actualizarán su costo.
                    </p>
                    <div class="vmodal__foot">
                        <button type="button" class="mb mb--ghost" id="btnCancelarRecibir">Cancelar</button>
                        <button type="submit" class="mb mb--primary">Sí, recibir</button>
                    </div>
                </form>
            </div>
        @endif
    @endcan

    @can('compras.eliminar')
        @if ($purchase->status !== 'anulada')
            <div class="vmodal" id="modalAnularC" role="dialog" aria-modal="true">
                <form method="POST" action="{{ route('purchases.anular', $purchase) }}" class="vmodal__box">
                    @csrf
                    <h3>Anular la compra {{ $purchase->number }}</h3>
                    <p>
                        @if ($purchase->status === 'recibida')
                            Esta compra ya fue recibida: al anularla, la mercancía <b>saldrá</b>
                            del inventario con su movimiento de kardex.
                        @else
                            La compra queda marcada como anulada. El inventario no se toca
                            porque nunca se le dio entrada.
                        @endif
                    </p>
                    <label>
                        Motivo
                        <textarea name="motivo" rows="3" required minlength="5"
                                  placeholder="Ej.: el proveedor no despachó el pedido"></textarea>
                    </label>
                    <div class="vmodal__foot">
                        <button type="button" class="mb mb--ghost" id="btnCancelarAnularC">Cancelar</button>
                        <button type="submit" class="mb mb--danger">Sí, anular</button>
                    </div>
                </form>
            </div>
        @endif
    @endcan

    @push('styles')
    <style>
        .vgrid{ display:grid; gap:16px; grid-template-columns:minmax(0,1.55fr) minmax(0,1fr);
                align-items:start; }
        @media (max-width:980px){ .vgrid{ grid-template-columns:minmax(0,1fr); } }

        .cav{ display:flex; gap:12px; align-items:flex-start; margin-bottom:16px;
              padding:14px 16px; border-radius:12px; border:1px solid var(--line); }
        .cav > span{ display:grid; place-items:center; width:32px; height:32px; flex:none;
                     border-radius:9px; color:#fff; }
        .cav b{ display:block; font-size:13.6px; color:var(--ink); }
        .cav p{ margin:3px 0 0; font-size:12.6px; color:var(--muted); line-height:1.55; }
        .cav--warn{ border-color:rgba(150,112,60,.35); background:rgba(150,112,60,.06); }
        .cav--warn > span{ background:var(--warn); }
        .cav--ok{ border-color:rgba(62,125,92,.35); background:rgba(62,125,92,.06); }
        .cav--ok > span{ background:var(--ok); }
        .cav--bad{ border-color:rgba(150,80,79,.35); background:rgba(150,80,79,.06); }
        .cav--bad > span{ background:var(--bad); }

        .ctot{ padding:13px 18px 16px; border-top:1px solid var(--line-2); background:var(--paper); }
        .ctot > div{ display:flex; justify-content:space-between; align-items:baseline;
                     font-size:12.8px; color:var(--muted); padding:3px 0; }
        .ctot > div b{ color:var(--ink-2); font-weight:600; font-variant-numeric:tabular-nums; }
        .ctot__big{ margin-top:7px; padding-top:9px !important; border-top:1px solid var(--line);
                    font-size:14px !important; color:var(--ink) !important; }
        .ctot__big b{ font-size:22px !important; font-weight:700 !important; letter-spacing:-.03em;
                      color:var(--a-600) !important; }

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
        .vmodal__box textarea{ width:100%; margin-top:6px; padding:9px 11px; border:1px solid var(--line);
                               border-radius:9px; font:inherit; font-size:13px; color:var(--ink);
                               resize:vertical; }
        .vmodal__foot{ display:flex; gap:9px; justify-content:flex-end; margin-top:16px; }
    </style>
    @endpush

    @push('scripts')
    <script>
    (function () {
        function enlazar(idBoton, idModal, idCancelar) {
            var b = document.getElementById(idBoton);
            var m = document.getElementById(idModal);
            var c = document.getElementById(idCancelar);
            if (!b || !m) return;

            b.addEventListener('click', function () {
                m.classList.add('is-on');
                setTimeout(function () { var t = m.querySelector('textarea'); if (t) t.focus(); }, 240);
            });
            if (c) c.addEventListener('click', function () { m.classList.remove('is-on'); });
            m.addEventListener('click', function (e) { if (e.target === m) m.classList.remove('is-on'); });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') m.classList.remove('is-on');
            });
        }

        enlazar('btnRecibir', 'modalRecibir', 'btnCancelarRecibir');
        enlazar('btnAnularC', 'modalAnularC', 'btnCancelarAnularC');
    })();
    </script>
    @endpush
</x-mus.page>
