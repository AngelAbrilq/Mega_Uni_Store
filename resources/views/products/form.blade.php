@php
    /**
     * Formulario compartido por crear y editar.
     * $item es null al crear y el producto al editar.
     *
     * Las categorías se muestran como "Papelería › Cuadernos" para que se
     * entienda el nivel sin tener que abrir el árbol.
     */
    $opcCategorias = ($categories ?? collect())
        ->mapWithKeys(fn ($c) => [$c->id => $c->parent ? $c->parent->name . ' › ' . $c->name : $c->name])
        ->all();
    asort($opcCategorias);

    $opcUnidades = ($units ?? collect())
        ->mapWithKeys(fn ($u) => [$u->id => $u->name . ' (' . $u->symbol . ')'])
        ->all();

    $opcImpuestos = ($taxes ?? collect())
        ->mapWithKeys(fn ($t) => [$t->id => $t->label])
        ->all();

    $opcProveedores = ($suppliers ?? collect())
        ->pluck('name', 'id')
        ->all();

    /* AT-PRI-001: impuestos adicionales marcados. */
    $extrasActuales = array_map('intval', (array) (old('extra_taxes')
        ?? ($item?->extraTaxes?->pluck('id')->all() ?? [])));

    /* TBL-TPL-001: filas de proveedores (las guardadas + una vacía para agregar). */
    $filasProveedores = old('proveedores') ?? ($item?->suppliers?->map(fn ($s) => [
        'supplier_id'    => $s->id,
        'supplier_sku'   => $s->pivot->supplier_sku,
        'supplier_price' => $s->pivot->supplier_price !== null ? (float) $s->pivot->supplier_price : null,
        'lead_time_days' => $s->pivot->lead_time_days,
    ])->values()->all() ?? []);
    $filasProveedores = array_values($filasProveedores);
    $filasProveedores[] = ['supplier_id' => null, 'supplier_sku' => null, 'supplier_price' => null, 'lead_time_days' => null];
    $preferido = (int) (old('proveedor_preferido') ?? ($item?->suppliers?->firstWhere('pivot.is_preferred', true)?->id ?? $item?->supplier_id));

    /* TBL-PPA-001: valores de atributos. */
    $valoresAtributos = old('atributos') ?? ($item?->attributeValues?->mapWithKeys(fn ($a) => [$a->id => $a->pivot->value])->all() ?? []);
@endphp

<div class="mf">
    <div class="mf__grid">
        <x-mus.field name="name" label="Nombre del producto" :value="$item?->name" :required="true"
                     :wide="true" max="200" />

        <x-mus.field name="sku" label="SKU / código interno" :value="$item?->sku" max="50"
                     hint="Tu propio código. Si lo dejas vacío, no pasa nada." />

        <x-mus.field name="barcode" label="Código de barras" :value="$item?->barcode" max="50"
                     hint="EAN o UPC. No puede repetirse." />

        <x-mus.select name="category_id" label="Categoría" :value="$item?->category_id"
                      empty="Sin categoría" :options="$opcCategorias" />

        <x-mus.select name="unit_id" label="Unidad de medida" :value="$item?->unit_id"
                      empty="Sin unidad" :options="$opcUnidades" />

        <x-mus.select name="tax_id" label="Impuesto principal" :value="$item?->tax_id"
                      empty="Sin impuesto" :options="$opcImpuestos"
                      hint="Normalmente el IVA. Los adicionales se marcan más abajo." />

        <x-mus.field name="cost" label="Costo" type="number" step="0.01" prefix="$" min="0"
                     :value="$item?->cost ?? 0" :required="true"
                     hint="Lo que te cuesta a ti." />

        <x-mus.field name="price" label="Precio de venta" type="number" step="0.01" prefix="$" min="0"
                     :value="$item?->price ?? 0" :required="true"
                     hint="Lo que paga el cliente." />

        <x-mus.field name="stock" label="Existencias" type="number" step="1" min="0"
                     :value="$item?->stock ?? 0" :required="true"
                     hint="Unidades disponibles hoy." />

        <x-mus.field name="min_stock" label="Stock mínimo" type="number" step="1" min="0"
                     :value="$item?->min_stock ?? 0" :required="true"
                     hint="Cuando baje de aquí, hay que reponer." />

        {{-- Lo que convierte un producto en un servicio agendable. Va aquí,
             en el mismo formulario, y no en una pantalla aparte: el corte de
             dama tiene costo, precio e impuesto igual que un martillo. Lo
             único que lo distingue es que ocupa una hora del día. --}}
        <x-mus.field name="duracion_minutos" label="Dura (minutos)" type="number" step="5" min="0" max="600"
                     :value="$item?->duracion_minutos"
                     hint="Solo para servicios que se agendan. Vacío = no aparece en la agenda." />

        <x-mus.imagen name="imagen" label="Foto del producto" :actual="$item?->imagen"
                      hint="JPG, PNG o WEBP · máximo 2 MB. Se guarda en el servidor; en la base de datos solo queda la ruta." />

        <x-mus.textarea name="description" label="Descripción" :value="$item?->description" :rows="3" />

        <x-mus.toggle name="is_active" label="Producto activo"
                      hint="Los inactivos no se ofrecen al vender."
                      :checked="$item?->is_active ?? true" />

        <x-mus.toggle name="is_public" label="Visible en la tienda pública"
                      hint="Apagado, el producto existe solo puertas adentro. Nada sale a internet por accidente."
                      :checked="$item?->is_public ?? false" />
    </div>
</div>

{{-- ═════════ AT-PRI-001 · Impuestos adicionales ═════════ --}}
@if (($taxes ?? collect())->count() > 1)
    <section class="psec" aria-labelledby="psec-imp">
        <header class="psec__head">
            <span class="psec__ico"><x-mus.icon name="percent" :w="15" /></span>
            <div>
                <b id="psec-imp">Impuestos adicionales</b>
                <span>Se suman al principal al vender: impoconsumo, bolsa plástica, estampillas…</span>
            </div>
        </header>
        @error('extra_taxes.*')<p class="mfld__err"><x-mus.icon name="alert" :w="12" /><span>{{ $message }}</span></p>@enderror
        <div class="pchips">
            @foreach ($taxes as $t)
                <label class="pchip">
                    <input type="checkbox" name="extra_taxes[]" value="{{ $t->id }}"
                           @checked(in_array($t->id, $extrasActuales, true))>
                    <span>{{ $t->label }}</span>
                </label>
            @endforeach
        </div>
        <input type="hidden" name="proveedores_presentes" value="1">
    </section>
@else
    <input type="hidden" name="proveedores_presentes" value="1">
@endif

{{-- ═════════ TBL-TPL-001 · Proveedores del producto ═════════ --}}
<section class="psec" aria-labelledby="psec-prov">
    <header class="psec__head">
        <span class="psec__ico"><x-mus.icon name="truck" :w="15" /></span>
        <div>
            <b id="psec-prov">Proveedores</b>
            <span>Quién te lo vende, a qué precio y en cuántos días. El preferido es el que se sugiere al comprar.</span>
        </div>
    </header>
    @error('proveedor_preferido')<p class="mfld__err"><x-mus.icon name="alert" :w="12" /><span>{{ $message }}</span></p>@enderror
    @error('proveedores.*.supplier_id')<p class="mfld__err"><x-mus.icon name="alert" :w="12" /><span>{{ $message }}</span></p>@enderror

    <div class="ptab" id="pProv">
        <div class="ptab__row ptab__row--head" aria-hidden="true">
            <span>Proveedor</span><span>Su código</span><span>Precio</span><span>Entrega (días)</span><span>Preferido</span>
        </div>
        @foreach ($filasProveedores as $i => $fila)
            <div class="ptab__row" data-fila>
                <label class="ptab__cell"><span class="ptab__lab">Proveedor</span>
                    <select name="proveedores[{{ $i }}][supplier_id]" class="mfld__in">
                        <option value="">—</option>
                        @foreach ($opcProveedores as $id => $nombre)
                            <option value="{{ $id }}" @selected((int) ($fila['supplier_id'] ?? 0) === (int) $id)>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ptab__cell"><span class="ptab__lab">Su código</span>
                    <input type="text" name="proveedores[{{ $i }}][supplier_sku]" maxlength="60" class="mfld__in"
                           value="{{ $fila['supplier_sku'] ?? '' }}" placeholder="Opcional">
                </label>
                <label class="ptab__cell"><span class="ptab__lab">Precio</span>
                    <input type="number" step="0.01" min="0" name="proveedores[{{ $i }}][supplier_price]" class="mfld__in"
                           value="{{ $fila['supplier_price'] ?? '' }}" placeholder="$">
                </label>
                <label class="ptab__cell"><span class="ptab__lab">Entrega (días)</span>
                    <input type="number" step="1" min="0" max="365" name="proveedores[{{ $i }}][lead_time_days]" class="mfld__in"
                           value="{{ $fila['lead_time_days'] ?? '' }}" placeholder="—">
                </label>
                <label class="ptab__cell ptab__cell--radio"><span class="ptab__lab">Preferido</span>
                    <input type="radio" name="proveedor_preferido" value="{{ $fila['supplier_id'] ?? '' }}" data-pref
                           @checked($preferido && (int) ($fila['supplier_id'] ?? 0) === $preferido)
                           aria-label="Proveedor preferido">
                </label>
            </div>
        @endforeach
    </div>
    <button type="button" class="mb mb--ghost mb--sm" id="pProvAgregar">
        <x-mus.icon name="plus" :w="13" /> Agregar otro proveedor
    </button>
</section>

{{-- ═════════ TBL-PPA-001 · Atributos ═════════ --}}
@if (($attributes ?? collect())->count())
    <section class="psec" aria-labelledby="psec-atr">
        <header class="psec__head">
            <span class="psec__ico"><x-mus.icon name="tag" :w="15" /></span>
            <div>
                <b id="psec-atr">Atributos</b>
                <span>Marca, talla, color… Déjalo vacío si no aplica a este producto.</span>
            </div>
        </header>
        <div class="mf__grid">
            @foreach ($attributes as $atr)
                @php $err = $errors->first("atributos.{$atr->id}"); $val = $valoresAtributos[$atr->id] ?? ''; @endphp
                <div class="{{ $err ? 'mfld--err' : '' }}">
                    <label class="mfld__lab" for="atr_{{ $atr->id }}">{{ $atr->name }}</label>
                    <div class="mfld__box">
                        @if ($atr->type === 'booleano')
                            <select id="atr_{{ $atr->id }}" name="atributos[{{ $atr->id }}]" class="mfld__in">
                                <option value="">—</option>
                                <option value="Sí" @selected(in_array(mb_strtolower($val), ['si', 'sí'], true))>Sí</option>
                                <option value="No" @selected(mb_strtolower($val) === 'no')>No</option>
                            </select>
                        @else
                            <input id="atr_{{ $atr->id }}" name="atributos[{{ $atr->id }}]" class="mfld__in"
                                   type="{{ $atr->type === 'numero' ? 'number' : 'text' }}" step="any"
                                   maxlength="150" value="{{ $val }}">
                        @endif
                    </div>
                    @if ($err)<p class="mfld__err"><x-mus.icon name="alert" :w="12" /><span>{{ $err }}</span></p>@endif
                </div>
            @endforeach
        </div>
    </section>
@endif

<style>
    .psec{ margin-top:14px; padding:16px 18px 18px; border:1px solid var(--line);
           border-radius:12px; background:var(--paper); }
    .psec__head{ display:flex; align-items:flex-start; gap:11px; margin-bottom:12px; }
    .psec__ico{ display:grid; place-items:center; width:30px; height:30px; flex:none;
                border-radius:9px; background:var(--a-500); color:#fff; }
    .psec__head b{ display:block; font-size:13.5px; color:var(--ink); letter-spacing:-.01em; }
    .psec__head div > span{ display:block; font-size:12.2px; color:var(--muted); margin-top:1px; line-height:1.5; }

    .pchips{ display:flex; flex-wrap:wrap; gap:8px; }
    .pchip{ position:relative; cursor:pointer; }
    .pchip input{ position:absolute; opacity:0; pointer-events:none; }
    .pchip span{ display:inline-flex; align-items:center; min-height:34px; padding:0 13px; border-radius:999px;
                 border:1px solid var(--line); background:var(--card); font-size:12.6px; color:var(--ink);
                 transition:all .18s var(--e-soft); }
    .pchip:hover span{ border-color:var(--a-400); }
    .pchip input:checked + span{ background:var(--a-500); border-color:var(--a-500); color:#fff; }
    .pchip input:focus-visible + span{ box-shadow:0 0 0 3px rgba(46,110,168,.25); }

    .ptab{ display:grid; gap:8px; margin-bottom:10px; }
    .ptab__row{ display:grid; gap:8px; align-items:center;
                grid-template-columns:minmax(0,2fr) minmax(0,1.1fr) minmax(0,1fr) minmax(0,.9fr) 74px; }
    .ptab__row--head span{ font-size:10.8px; letter-spacing:.06em; text-transform:uppercase;
                           color:var(--muted-2); font-weight:700; }
    .ptab__row--head span:last-child{ text-align:center; }
    .ptab__cell{ display:block; min-width:0; }
    .ptab__cell .mfld__in{ width:100%; height:38px; }
    .ptab__cell--radio{ display:flex; justify-content:center; }
    .ptab__cell--radio input{ width:18px; height:18px; accent-color:var(--a-500); cursor:pointer; }
    .ptab__lab{ display:none; }

    @media (max-width:760px){
        .ptab__row--head{ display:none; }
        .ptab__row{ grid-template-columns:1fr 1fr; padding:12px; border:1px solid var(--line-2);
                    border-radius:10px; background:var(--card); }
        .ptab__cell:first-child{ grid-column:1 / -1; }
        .ptab__lab{ display:block; font-size:11px; color:var(--muted); margin-bottom:3px; }
        .ptab__cell--radio{ justify-content:flex-start; flex-direction:column; }
        .ptab__cell .mfld__in{ height:42px; font-size:16px; }
    }
</style>

<script>
(function () {
    // El impuesto principal no se ofrece también como adicional.
    var principal = document.getElementById('f_tax_id');
    function ocultarPrincipal() {
        document.querySelectorAll('input[name="extra_taxes[]"]').forEach(function (chk) {
            var esPrincipal = principal && chk.value === principal.value;
            chk.closest('.pchip').hidden = esPrincipal;
            if (esPrincipal) chk.checked = false;
        });
    }
    if (principal) { principal.addEventListener('change', ocultarPrincipal); ocultarPrincipal(); }
})();

(function () {
    var caja = document.getElementById('pProv');
    var boton = document.getElementById('pProvAgregar');
    if (!caja || !boton) return;

    // El radio «preferido» vale lo que diga el selector de su fila.
    function enlazar(fila) {
        var sel = fila.querySelector('select');
        var radio = fila.querySelector('[data-pref]');
        sel.addEventListener('change', function () { radio.value = sel.value; });
    }
    caja.querySelectorAll('[data-fila]').forEach(enlazar);

    boton.addEventListener('click', function () {
        var filas = caja.querySelectorAll('[data-fila]');
        var nueva = filas[filas.length - 1].cloneNode(true);
        var i = filas.length;
        nueva.querySelectorAll('[name^="proveedores["]').forEach(function (el) {
            el.name = el.name.replace(/proveedores\[\d+\]/, 'proveedores[' + i + ']');
            el.value = '';
        });
        var radio = nueva.querySelector('[data-pref]');
        radio.checked = false; radio.value = '';
        caja.appendChild(nueva);
        enlazar(nueva);
        nueva.querySelector('select').focus();
    });
})();
</script>
