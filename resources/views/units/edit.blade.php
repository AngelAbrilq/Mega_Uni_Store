<x-mus.page title="Editar unidad" subtitle="{{ $unit->name }}" icon="ruler"
            :crumbs="['Unidades' => route('units.index'), 'Editar' => null]">

    <x-slot name="actions">
        <x-mus.btn href="{{ route('units.show', $unit) }}" icon="eye">Ver detalle</x-mus.btn>
    </x-slot>

    <form method="POST" action="{{ route('units.update', $unit) }}" novalidate>
        @csrf
        @method('PUT')

        <x-mus.panel title="Datos de la unidad" sub="Nombre, símbolo y tipo de medida">
            @include('units.form', ['item' => $unit])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('units.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cambios</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
