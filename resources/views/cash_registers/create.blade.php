<x-mus.page title="Nueva caja" subtitle="Caja registradora del local activo" icon="money"
            :crumbs="['Cajas registradoras' => route('cash_registers.index'), 'Nueva' => null]">

    <form method="POST" action="{{ route('cash_registers.store') }}" novalidate>
        @csrf

        <x-mus.panel title="Datos de la caja" sub="Cada turno de caja se abre en una de estas cajas">
            @include('cash_registers.form', ['item' => null])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('cash_registers.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar caja</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
