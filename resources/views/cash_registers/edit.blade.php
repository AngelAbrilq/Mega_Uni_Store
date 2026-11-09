<x-mus.page title="Editar caja" subtitle="{{ $caja->etiqueta }}" icon="money"
            :crumbs="['Cajas registradoras' => route('cash_registers.index'), 'Editar' => null]">

    <form method="POST" action="{{ route('cash_registers.update', $caja) }}" novalidate>
        @csrf
        @method('PUT')

        <x-mus.panel title="Datos de la caja" sub="Los turnos ya cerrados conservan el nombre con que se hicieron">
            @include('cash_registers.form', ['item' => $caja])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('cash_registers.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cambios</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
