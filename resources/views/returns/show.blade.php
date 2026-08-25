@php use App\Support\Formato; @endphp

<x-mus.page title="Devolución {{ $devolucion->number }}"
            subtitle="Sobre la venta {{ $devolucion->sale->number ?? '' }} · {{ Formato::enPalabras($devolucion->returned_at, 'D [de] MMMM [de] YYYY, HH:mm') }}"
            icon="back"
            :crumbs="['Devoluciones' => route('returns.index'), 'Detalle' => null]">

    <x-slot name="actions">
        <x-mus.btn href="{{ route('sales.show', $devolucion->sale_id) }}" icon="money">Ver la venta</x-mus.btn>
    </x-slot>

    <div class="cav {{ $devolucion->restock ? 'cav--ok' : 'cav--warn' }}" data-reveal>
        <span><x-mus.icon :name="$devolucion->restock ? 'check' : 'alert'" :w="16" stroke-width="2.4" /></span>
        <div>
            <b>{{ $devolucion->restock ? 'La mercancía volvió al inventario' : 'La mercancía no volvió al inventario' }}</b>
            <p>
                @if ($devolucion->restock)
                    Cada producto sumó a su stock con un movimiento de kardex a nombre de
                    {{ $devolucion->user->name ?? 'quien la recibió' }}.
                @else
                    Se registró la devolución al cliente pero los productos no se
                    reingresaron: llegaron dañados o vencidos.
                @endif
            </p>
            <q>{{ $devolucion->reason }}</q>
        </div>
    </div>

    <div class="vgrid">
        <x-mus.panel title="Productos devueltos" :pad="false"
                     sub="{{ $devolucion->items->count() }} renglones">
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="num">Cantidad</th>
                            <th class="num">Precio</th>
                            <th class="num">Impuesto</th>
                            <th class="num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($devolucion->items as $item)
                            <tr>
                                <td>
                                    @if ($item->product)
                                        <a href="{{ route('inventory.kardex', $item->product) }}"><b>{{ $item->name }}</b></a>
                                        <span class="sub">existencias hoy: {{ $item->product->stock_texto }}</span>
                                    @else
                                        <b>{{ $item->name }}</b>
                                    @endif
                                </td>
                                <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="num"><i class="moneda">$</i>{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                                <td class="num">
                                    @if ((float) $item->tax_amount > 0)
                                        ${{ number_format((float) $item->tax_amount, 0, ',', '.') }}
                                    @else — @endif
                                </td>
                                <td class="num"><b><i class="moneda">$</i>{{ number_format((float) $item->total, 0, ',', '.') }}</b></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="ctot">
                <div><span>Subtotal</span><b><i class="moneda">$</i>{{ number_format((float) $devolucion->subtotal, 0, ',', '.') }}</b></div>
                <div><span>Impuestos</span><b><i class="moneda">$</i>{{ number_format((float) $devolucion->tax_total, 0, ',', '.') }}</b></div>
                <div class="ctot__big"><span>Devuelto al cliente</span>
                    <b><i class="moneda">$</i>{{ number_format((float) $devolucion->total, 0, ',', '.') }}</b></div>
            </div>
        </x-mus.panel>

        <x-mus.panel title="Datos">
            <dl class="mdl">
                <div><dt>Número</dt><dd>{{ $devolucion->number }}</dd></div>
                <div>
                    <dt>Venta original</dt>
                    <dd><a href="{{ route('sales.show', $devolucion->sale_id) }}">{{ $devolucion->sale->number ?? '—' }}</a></dd>
                </div>
                <div>
                    <dt>Cliente</dt>
                    <dd>{{ $devolucion->sale?->customer?->full_name ?? 'Consumidor final' }}</dd>
                </div>
                <div><dt>Recibió</dt><dd>{{ $devolucion->user->name ?? '—' }}</dd></div>
                <div><dt>Fecha</dt><dd>{{ Formato::enPalabras($devolucion->returned_at, 'D MMM YYYY, HH:mm') }}</dd></div>
                <div>
                    <dt>Reingresó a bodega</dt>
                    <dd>{{ $devolucion->restock ? 'Sí' : 'No' }}</dd>
                </div>
            </dl>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('returns.index') }}" icon="back" :block="true">
                    Volver al listado
                </x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </div>

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
        .cav q{ display:block; margin-top:6px; font-size:12.6px; color:var(--ink-2); font-style:italic; }
        .cav--ok{ border-color:rgba(62,125,92,.35); background:rgba(62,125,92,.06); }
        .cav--ok > span{ background:var(--ok); }
        .cav--warn{ border-color:rgba(150,112,60,.35); background:rgba(150,112,60,.06); }
        .cav--warn > span{ background:var(--warn); }

        .ctot{ padding:13px 18px 16px; border-top:1px solid var(--line-2); background:var(--paper); }
        .ctot > div{ display:flex; justify-content:space-between; align-items:baseline;
                     font-size:12.8px; color:var(--muted); padding:3px 0; }
        .ctot > div b{ color:var(--ink-2); font-weight:600; font-variant-numeric:tabular-nums; }
        .ctot__big{ margin-top:7px; padding-top:9px !important; border-top:1px solid var(--line);
                    font-size:14px !important; color:var(--ink) !important; }
        .ctot__big b{ font-size:22px !important; font-weight:700 !important; letter-spacing:-.03em;
                      color:var(--bad) !important; }
    </style>
    @endpush
</x-mus.page>
