@php
    /** Renglones ya guardados, para reconstruir la tabla al editar. */
    $lineas = old('items', ($purchase ?? null)?->items->map(fn ($i) => [
        'product_id'  => $i->product_id,
        'quantity'    => (float) $i->quantity,
        'unit_cost'   => (float) $i->unit_cost,
        'tax_rate'    => (float) $i->tax_rate,
        'update_cost' => (bool) $i->update_cost,
    ])->all() ?? []);
@endphp

<x-mus.panel title="Datos de la compra" sub="Proveedor, factura y fecha">
    <div class="mf">
        <div class="mf__grid">
            <x-mus.select name="supplier_id" label="Proveedor" :required="true"
                          :value="($purchase ?? null)?->supplier_id"
                          empty="Elige un proveedor"
                          :options="$proveedores->pluck('name', 'id')->all()" />

            <x-mus.field name="invoice_number" label="Número de factura del proveedor"
                         :value="($purchase ?? null)?->invoice_number" max="50"
                         hint="Opcional. El que trae el documento del proveedor." />

            <x-mus.field name="ordered_at" label="Fecha de la compra" type="date"
                         :value="($purchase ?? null)?->ordered_at?->format('Y-m-d') ?? now()->format('Y-m-d')" />

            <x-mus.textarea name="notes" label="Observaciones" :rows="2"
                            :value="($purchase ?? null)?->notes" />
        </div>
    </div>
</x-mus.panel>

<x-mus.panel title="Productos" sub="Lo que se le compra al proveedor" :pad="false">
    <x-slot name="tools">
        <div class="cadd">
            <select id="cProd" class="cadd__sel">
                <option value="">Elegir producto…</option>
                @foreach ($productos as $p)
                    <option value="{{ $p['id'] }}" data-costo="{{ $p['costo'] }}"
                            data-sku="{{ $p['sku'] }}" data-stock="{{ $p['stock'] }}">
                        {{ $p['sku'] ? $p['sku'] . ' · ' : '' }}{{ $p['nombre'] }}
                    </option>
                @endforeach
            </select>
            <button type="button" class="mb mb--primary mb--sm" id="cAgregar">
                <x-mus.icon name="plus" :w="14" stroke-width="2.2" /> Agregar
            </button>
        </div>
    </x-slot>

    @error('items')
        <p class="mfld__err" style="margin:12px 18px 0">
            <x-mus.icon name="alert" :w="12" stroke-width="2.2" /><span>{{ $message }}</span>
        </p>
    @enderror

    <div class="mt-wrap">
        <table class="mt" id="cTabla">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th class="num">Cantidad</th>
                    <th class="num">Costo unitario</th>
                    <th class="num">IVA %</th>
                    <th class="num">Total</th>
                    <th>Actualiza costo</th>
                    <th class="act"></th>
                </tr>
            </thead>
            <tbody id="cCuerpo"></tbody>
        </table>
    </div>

    <div class="cvacio" id="cVacio">
        <x-mus.icon name="box" :w="24" stroke-width="1.5" />
        <p>Todavía no hay productos en esta compra.</p>
    </div>

    <div class="ctot">
        <div><span>Subtotal</span><b id="cSub">$0</b></div>
        <div><span>Impuestos</span><b id="cImp">$0</b></div>
        <div class="ctot__big"><span>Total</span><b id="cTot">$0</b></div>
    </div>

    <x-slot name="foot">
        <x-mus.btn href="{{ route('purchases.index') }}" icon="back">Cancelar</x-mus.btn>
        <x-mus.btn type="submit" variant="primary" icon="save">
            {{ isset($purchase) ? 'Guardar cambios' : 'Registrar compra' }}
        </x-mus.btn>
    </x-slot>
</x-mus.panel>

<style>
    .cadd{ display:flex; align-items:center; gap:8px; }
    .cadd__sel{ height:34px; padding:0 26px 0 10px; border:1px solid var(--line); border-radius:8px;
                background:#fff; font:inherit; font-size:12.5px; color:var(--ink); cursor:pointer;
                max-width:290px; }

    .cvacio{ display:grid; place-items:center; gap:9px; padding:38px 0; color:var(--muted-2); }
    .cvacio[hidden]{ display:none; }
    .cvacio p{ margin:0; font-size:12.7px; }

    #cTabla input{ width:100%; height:32px; padding:0 8px; text-align:right;
                   border:1px solid var(--line); border-radius:7px; background:#fff;
                   font:inherit; font-size:12.6px; color:var(--ink);
                   font-variant-numeric:tabular-nums; outline:0; }
    #cTabla input:focus{ border-color:var(--a-400); box-shadow:0 0 0 3px rgba(46,110,168,.12); }
    #cTabla td.num{ width:120px; }
    #cTabla .cchk{ display:flex; justify-content:center; }
    #cTabla .cchk input{ width:auto; height:auto; accent-color:var(--a-500); }
    .cquitar{ border:0; background:transparent; cursor:pointer; color:var(--muted-2);
              font:inherit; font-size:17px; line-height:1; padding:2px 6px; }
    .cquitar:hover{ color:var(--bad); }

    .ctot{ padding:13px 18px 16px; border-top:1px solid var(--line-2); background:var(--paper); }
    .ctot > div{ display:flex; justify-content:space-between; align-items:baseline;
                 font-size:12.8px; color:var(--muted); padding:3px 0; }
    .ctot > div b{ color:var(--ink-2); font-weight:600; font-variant-numeric:tabular-nums; }
    .ctot__big{ margin-top:7px; padding-top:9px !important; border-top:1px solid var(--line);
                font-size:14px !important; color:var(--ink) !important; }
    .ctot__big b{ font-size:22px !important; font-weight:700 !important; letter-spacing:-.03em;
                  color:var(--a-600) !important; }

    /* ── Celular ──
       La tabla de renglones pasa a modo tarjeta (eso lo hace el layout),
       así que aquí solo se sueltan los anchos fijos y se agrandan los
       campos para poder escribir con el dedo. */
    @media (max-width:760px){
        .cadd{ flex-wrap:wrap; width:100%; min-width:0; }
        .cadd__sel{ flex:1 1 100%; width:100%; min-width:0; max-width:100%;
                    height:42px; font-size:16px; }
        .cadd .mb{ flex:1 1 100%; }

        #cTabla td.num{ width:auto; }
        #cTabla input{ width:150px; height:38px; font-size:16px; }
        #cTabla .cchk{ justify-content:flex-end; }
        #cTabla .cchk input{ width:20px; height:20px; }
        .cquitar{ font-size:22px; padding:2px 8px; }
        .ctot{ padding:13px 14px 16px; }
    }
</style>

<script>
window.COMPRA_LINEAS = @json(array_values((array) $lineas));
</script>
<script>
/* Tabla de renglones de la compra: agregar, quitar y recalcular. */
(function () {
    'use strict';

    var sel    = document.getElementById('cProd');
    var cuerpo = document.getElementById('cCuerpo');
    var vacio  = document.getElementById('cVacio');
    if (!sel || !cuerpo) return;

    var n = 0;

    function pesos(v) { return '$' + Math.round(v).toLocaleString('es-CO'); }

    function recalcular() {
        var sub = 0, imp = 0;

        cuerpo.querySelectorAll('tr').forEach(function (fila) {
            var cant  = Number(fila.querySelector('[data-cant]').value) || 0;
            var costo = Number(fila.querySelector('[data-costo]').value) || 0;
            var tasa  = Number(fila.querySelector('[data-tasa]').value) || 0;

            var s = cant * costo;
            var i = s * tasa / 100;

            fila.querySelector('[data-total]').textContent = pesos(s + i);
            sub += s;
            imp += i;
        });

        document.getElementById('cSub').textContent = pesos(sub);
        document.getElementById('cImp').textContent = pesos(imp);
        document.getElementById('cTot').textContent = pesos(sub + imp);

        vacio.hidden = cuerpo.children.length > 0;
    }

    function agregar(id, cant, costo, tasa, actualiza) {
        var opt = sel.querySelector('option[value="' + id + '"]');
        if (!opt) return;

        // Si ya está, solo se suma la cantidad.
        var previa = cuerpo.querySelector('tr[data-id="' + id + '"]');
        if (previa) {
            var c = previa.querySelector('[data-cant]');
            c.value = (Number(c.value) || 0) + (cant || 1);
            recalcular();
            return;
        }

        var i = n++;
        var tr = document.createElement('tr');
        tr.dataset.id = id;
        tr.innerHTML =
            '<td>' +
                '<b class="cnom"></b><span class="sub cskuu"></span>' +
                '<input type="hidden" name="items[' + i + '][product_id]" value="' + id + '">' +
            '</td>' +
            '<td class="num"><input type="number" step="0.001" min="0.001" data-cant ' +
                'name="items[' + i + '][quantity]" value="' + (cant || 1) + '"></td>' +
            '<td class="num"><input type="number" step="1" min="0" data-costo ' +
                'name="items[' + i + '][unit_cost]" value="' + (costo != null ? costo : opt.dataset.costo) + '"></td>' +
            '<td class="num"><input type="number" step="0.01" min="0" max="100" data-tasa ' +
                'name="items[' + i + '][tax_rate]" value="' + (tasa != null ? tasa : 19) + '"></td>' +
            '<td class="num"><b data-total>$0</b></td>' +
            '<td><span class="cchk">' +
                '<input type="hidden" name="items[' + i + '][update_cost]" value="0">' +
                '<input type="checkbox" name="items[' + i + '][update_cost]" value="1"' +
                (actualiza === false ? '' : ' checked') + '></span></td>' +
            '<td class="act"><button type="button" class="cquitar" aria-label="Quitar">&times;</button></td>';

        tr.querySelector('.cnom').textContent = opt.textContent.split(' · ').slice(-1)[0].trim();
        tr.querySelector('.cskuu').textContent = (opt.dataset.sku || '') +
            ' · existencias: ' + opt.dataset.stock;

        cuerpo.appendChild(tr);
        recalcular();
    }

    document.getElementById('cAgregar').addEventListener('click', function () {
        if (sel.value) { agregar(sel.value); sel.value = ''; }
    });

    sel.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); if (sel.value) { agregar(sel.value); sel.value = ''; } }
    });

    cuerpo.addEventListener('input', recalcular);
    cuerpo.addEventListener('click', function (e) {
        if (e.target.closest('.cquitar')) { e.target.closest('tr').remove(); recalcular(); }
    });

    /* Reconstruye los renglones guardados (edición o error de validación). */
    (window.COMPRA_LINEAS || []).forEach(function (l) {
        agregar(l.product_id, Number(l.quantity), Number(l.unit_cost),
                Number(l.tax_rate || 0), l.update_cost != 0);
    });

    recalcular();
})();
</script>
