<x-mus.page title="Ajustar existencias" subtitle="{{ $product->name }}" icon="stock"
            :crumbs="['Inventario' => route('inventory.index'), 'Ajuste' => null]">

    <form method="POST" action="{{ route('inventory.ajuste.guardar', $product) }}" novalidate>
        @csrf

        <x-mus.panel title="Movimiento manual"
                     sub="Saldo actual: {{ rtrim(rtrim(number_format((float) $product->stock, 2, ',', '.'), '0'), ',') }} {{ $product->unit->symbol ?? 'und' }}">

            <div class="mf">
                <div class="mf__grid">
                    <x-mus.select name="modo" label="Tipo de ajuste" :required="true" :value="'conteo'"
                                  :options="[
                                      'conteo'  => 'Conteo físico — dejar el saldo en…',
                                      'entrada' => 'Entrada — sumar unidades',
                                      'salida'  => 'Salida — restar unidades',
                                  ]"
                                  hint="El conteo físico registra solo la diferencia." />

                    <x-mus.field name="cantidad" label="Cantidad" type="number" step="1" min="0"
                                 :required="true" :value="(int) $product->stock" />

                    <x-mus.textarea name="motivo" label="Motivo" :rows="2" :required="true"
                                    hint="Queda guardado en el kardex. Ej.: «rotura de 3 unidades» o «conteo del 19 de agosto»." />
                </div>
            </div>

            <div class="anota">
                <x-mus.icon name="info" :w="15" />
                <p>
                    Ningún ajuste borra historia: se agrega un movimiento nuevo al kardex
                    con tu nombre, la fecha y el motivo. Así siempre se puede reconstruir
                    cómo llegó el inventario al saldo que tiene.
                </p>
            </div>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('inventory.kardex', $product) }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Aplicar ajuste</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>

    @push('styles')
    <style>
        .anota{ display:flex; gap:10px; align-items:flex-start; margin-top:14px; padding:12px 14px;
                border:1px solid var(--line); border-radius:11px; background:var(--paper); }
        .anota > svg{ flex:none; margin-top:1px; color:var(--a-500); }
        .anota p{ margin:0; font-size:12.5px; color:var(--muted); line-height:1.6; }
    </style>
    @endpush
</x-mus.page>
