<x-mus.page title="Nueva unidad" subtitle="Define cómo se mide este producto" icon="ruler"
            :crumbs="['Unidades' => route('units.index'), 'Nuevo' => null]">

    <form method="POST" action="{{ route('units.store') }}" novalidate>
        @csrf

        <x-mus.panel title="Datos de la unidad" sub="Nombre, símbolo y tipo de medida">
            @include('units.form', ['item' => null])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('units.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar unidad</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
