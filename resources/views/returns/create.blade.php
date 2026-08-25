@php use App\Support\Formato; @endphp

<x-mus.page title="Devolución sobre {{ $sale->number }}"
            subtitle="Elige qué se devuelve y cuánto" icon="back"
            :crumbs="['Ventas' => route('sales.index'), 'Devolución' => null]">

    <form method="POST" action="{{ route('returns.store', $sale) }}" novalidate>
        @csrf

        <div class="dgrid">
            <x-mus.panel title="Productos a devolver" :pad="false"
                         sub="Solo aparecen los que todavía se pueden devolver">
                @error('items')
                    <p class="mfld__err" style="margin:12px 18px 0">
                        <x-mus.icon name="alert" :w="12" stroke-width="2.2" /><span>{{ $message }}</span>
                    </p>
                @enderror

                <div class="mt-wrap">
                    <table class="mt" id="dTabla">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="num">Vendido</th>
                                <th class="num">Ya devuelto</th>
                                <th class="num">Devolver ahora</th>
                                <th class="num">Se le regresa</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pendientes as $i => $item)
                                <tr data-fila
                                    data-unit="{{ (float) $item->quantity > 0 ? round(((float) $item->subtotal + (float) $item->tax_amount) / (float) $item->quantity, 4) : 0 }}">
                                    <td>
                                        <b>{{ $item->name }}</b>
                                        <span class="sub">
                                            {{ $item->sku ?: '—' }} ·
                                            ${{ number_format((float) $item->unit_price, 0, ',', '.') }} c/u
                                        </span>
                                        <input type="hidden" name="items[{{ $i }}][sale_item_id]" value="{{ $item->id }}">
                                    </td>
                                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}</td>
                                    <td class="num">
                                        @if ((float) $item->returned_quantity > 0)
                                            <span style="color:var(--warn)">
                                                {{ rtrim(rtrim(number_format((float) $item->returned_quantity, 2, ',', '.'), '0'), ',') }}
                                            </span>
                                        @else — @endif
                                    </td>
                                    <td class="num">
                                        <span class="dcant">
                                            <button type="button" data-menos>−</button>
                                            <input type="number" step="1" min="0"
                                                   max="{{ $item->devolvible }}"
                                                   value="0" data-cant
                                                   name="items[{{ $i }}][quantity]">
                                            <button type="button" data-mas>+</button>
                                        </span>
                                        <span class="sub" style="display:block;margin-top:3px">
                                            máx. {{ rtrim(rtrim(number_format($item->devolvible, 2, ',', '.'), '0'), ',') }}
                                        </span>
                                    </td>
                                    <td class="num"><b data-linea>$0</b></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="dtot">
                    <div><span>Unidades a devolver</span><b id="dUnid">0</b></div>
                    <div class="dtot__big"><span>Total a regresar</span><b id="dTotal">$0</b></div>
                </div>
            </x-mus.panel>

            <div style="display:grid;gap:16px;grid-template-columns:minmax(0,1fr);align-content:start">
                <x-mus.panel title="Datos de la devolución">
                    <div class="mf">
                        <div class="mf__grid">
                            <x-mus.textarea name="motivo" label="Motivo" :rows="3" :required="true"
                                            hint="Queda guardado en el documento y en la auditoría." />

                            <x-mus.toggle name="restock" label="La mercancía vuelve a bodega"
                                          hint="Apágalo si el producto llegó dañado o vencido: en ese caso no suma al inventario."
                                          :checked="true" />
                        </div>
                    </div>

                    <div class="dnota">
                        <x-mus.icon name="info" :w="15" />
                        <p>
                            La venta {{ $sale->number }} no se modifica: la devolución queda como
                            un documento aparte que la referencia. Así el histórico de ventas
                            sigue cuadrando.
                        </p>
                    </div>

                    <x-slot name="foot">
                        <x-mus.btn href="{{ route('sales.show', $sale) }}" icon="back">Cancelar</x-mus.btn>
                        <x-mus.btn type="submit" variant="primary" icon="save" id="dGuardar">
                            Registrar devolución
                        </x-mus.btn>
                    </x-slot>
                </x-mus.panel>

                <x-mus.panel title="La venta original" sub="{{ Formato::enPalabras($sale->sold_at, 'D MMM YYYY, HH:mm') }}">
                    <dl class="mdl">
                        <div><dt>Número</dt><dd>{{ $sale->number }}</dd></div>
                        <div><dt>Cliente</dt><dd>{{ $sale->customer?->full_name ?? 'Consumidor final' }}</dd></div>
                        <div><dt>Total cobrado</dt><dd><i class="moneda">$</i>{{ number_format((float) $sale->total, 0, ',', '.') }}</dd></div>
                    </dl>
                </x-mus.panel>
            </div>
        </div>
    </form>

    <style>
        .dgrid{ display:grid; gap:16px; grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);
                align-items:start; }
        @media (max-width:1000px){ .dgrid{ grid-template-columns:minmax(0,1fr); } }

        .dcant{ display:inline-flex; align-items:center; border:1px solid var(--line);
                border-radius:8px; overflow:hidden; background:#fff; }
        .dcant button{ width:26px; height:28px; border:0; background:transparent; cursor:pointer;
                       font:inherit; font-size:14px; color:var(--ink-2); line-height:1; }
        .dcant button:hover{ background:var(--line-2); }
        .dcant input{ width:52px; height:28px; border:0; border-left:1px solid var(--line);
                      border-right:1px solid var(--line); text-align:center; font:inherit;
                      font-size:12.6px; color:var(--ink); outline:0; -moz-appearance:textfield; }
        .dcant input::-webkit-outer-spin-button, .dcant input::-webkit-inner-spin-button{
            -webkit-appearance:none; margin:0; }

        .dtot{ padding:13px 18px 16px; border-top:1px solid var(--line-2); background:var(--paper); }
        .dtot > div{ display:flex; justify-content:space-between; align-items:baseline;
                     font-size:12.8px; color:var(--muted); padding:3px 0; }
        .dtot > div b{ color:var(--ink-2); font-weight:600; font-variant-numeric:tabular-nums; }
        .dtot__big{ margin-top:7px; padding-top:9px !important; border-top:1px solid var(--line);
                    font-size:14px !important; color:var(--ink) !important; }
        .dtot__big b{ font-size:22px !important; font-weight:700 !important; letter-spacing:-.03em;
                      color:var(--bad) !important; }

        .dnota{ display:flex; gap:10px; align-items:flex-start; margin-top:14px; padding:12px 14px;
                border:1px solid var(--line); border-radius:11px; background:var(--paper); }
        .dnota > svg{ flex:none; margin-top:1px; color:var(--a-500); }
        .dnota p{ margin:0; font-size:12.5px; color:var(--muted); line-height:1.6; }

        /* ── Celular ──
           La tabla ya se ve como tarjetas; los botones de cantidad
           crecen para que se puedan tocar sin apuntar. */
        @media (max-width:760px){
            .dcant button{ width:34px; height:36px; font-size:16px; }
            .dcant input{ width:56px; height:36px; font-size:16px; }
            .dtot{ padding:13px 14px 16px; }
        }
    </style>

    <script>
    /* Suma en vivo lo que se le va a regresar al cliente. */
    (function () {
        var tabla = document.getElementById('dTabla');
        var boton = document.getElementById('dGuardar');
        if (!tabla) return;

        function pesos(v) { return '$' + Math.round(v).toLocaleString('es-CO'); }

        function recalcular() {
            var total = 0, unidades = 0;

            Array.prototype.forEach.call(tabla.querySelectorAll('[data-fila]'), function (fila) {
                var input = fila.querySelector('[data-cant]');
                var max   = Number(input.max) || 0;
                var n     = Number(input.value) || 0;

                if (n < 0) { n = 0; input.value = 0; }
                if (n > max) { n = max; input.value = max; }

                var linea = n * Number(fila.dataset.unit || 0);
                fila.querySelector('[data-linea]').textContent = pesos(linea);

                total += linea;
                unidades += n;
            });

            document.getElementById('dUnid').textContent = unidades;
            document.getElementById('dTotal').textContent = pesos(total);
            if (boton) boton.disabled = unidades === 0;
        }

        tabla.addEventListener('input', recalcular);
        tabla.addEventListener('click', function (e) {
            var fila = e.target.closest('[data-fila]');
            if (!fila) return;
            var input = fila.querySelector('[data-cant]');

            if (e.target.closest('[data-mas]')) { input.value = (Number(input.value) || 0) + 1; recalcular(); }
            if (e.target.closest('[data-menos]')) { input.value = (Number(input.value) || 0) - 1; recalcular(); }
        });

        recalcular();
    })();
    </script>
</x-mus.page>
