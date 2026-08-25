<x-mus.page title="Abrir turno de caja" subtitle="Cuenta la base antes de empezar a vender" icon="money"
            :crumbs="['Caja' => route('cash.index'), 'Abrir' => null]">

    <form method="POST" action="{{ route('cash.store') }}" novalidate>
        @csrf

        <x-mus.panel title="Base inicial" sub="El efectivo con el que arranca el cajón">
            <div class="mf">
                <div class="mf__grid">
                    <x-mus.field name="opening_amount" label="Monto de la base" type="number"
                                 step="1" min="0" prefix="$" :required="true" :value="0"
                                 hint="Lo que hay físicamente en la caja antes de la primera venta." />

                    <x-mus.textarea name="notes" label="Observaciones" :rows="2"
                                    hint="Opcional. Por ejemplo: quién entregó la base." />
                </div>
            </div>

            <div class="cnota">
                <x-mus.icon name="info" :w="15" />
                <p>
                    Al cerrar el turno el sistema calculará cuánto efectivo debería haber
                    (base + ventas cobradas en efectivo) y lo comparará con lo que cuentes.
                    La diferencia queda registrada.
                </p>
            </div>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('cash.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Abrir turno</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>

    @push('styles')
    <style>
        .cnota{ display:flex; gap:10px; align-items:flex-start; margin-top:14px; padding:12px 14px;
                border:1px solid var(--line); border-radius:11px; background:var(--paper); }
        .cnota > svg{ flex:none; margin-top:1px; color:var(--a-500); }
        .cnota p{ margin:0; font-size:12.5px; color:var(--muted); line-height:1.6; }
    </style>
    @endpush
</x-mus.page>
