@php
    $tipo = old('type', $item?->type ?? 'percentage');
@endphp
<div class="mf">
    <div class="mf__grid">
        <x-mus.field name="code" label="Código" :value="$item?->code" :required="true" max="40"
                     hint="Lo que el cliente dice en caja. Se guarda en mayúsculas: BIENVENIDA10"
                     style="text-transform:uppercase" />

        <x-mus.select name="type" label="Tipo de descuento" :required="true" :value="$tipo"
                      :options="\App\Models\Coupon::TIPOS" />

        <x-mus.field name="value" label="Valor" type="number" step="0.01" min="0" :required="true"
                     :value="$item?->value" hint="Porcentaje (10 = 10 %) o pesos, según el tipo." />

        <div id="cupTope" @if ($tipo === 'fixed') hidden @endif>
            <x-mus.field name="max_discount" label="Tope de descuento" type="number" step="1" min="0" prefix="$"
                         :value="$item?->max_discount" hint="Opcional · el 10 % nunca descontará más que esto." />
        </div>

        <x-mus.field name="min_purchase" label="Compra mínima" type="number" step="1" min="0" prefix="$"
                     :value="$item?->min_purchase" hint="Opcional · antes de impuestos." />

        <x-mus.field name="max_uses" label="Usos máximos" type="number" step="1" min="1"
                     :value="$item?->max_uses" hint="Vacío = sin límite." />

        <x-mus.field name="starts_at" label="Válido desde" type="date"
                     :value="$item?->starts_at?->format('Y-m-d')" />

        <x-mus.field name="ends_at" label="Válido hasta" type="date"
                     :value="$item?->ends_at?->format('Y-m-d')" hint="Vacío = no vence." />

        <x-mus.field name="description" label="Descripción" :value="$item?->description" :wide="true" max="200"
                     hint="Opcional · para qué campaña es." />

        <x-mus.toggle name="is_active" label="Cupón activo" hint="Apagado, el POS lo rechaza aunque esté vigente."
                      :checked="$item?->is_active ?? true" />
    </div>
</div>

<script>
(function () {
    var tipo = document.getElementById('f_type'), tope = document.getElementById('cupTope');
    if (!tipo || !tope) return;
    tipo.addEventListener('change', function () { tope.hidden = tipo.value === 'fixed'; });
})();
</script>
