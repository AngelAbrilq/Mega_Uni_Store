<x-mus.page title="Nuevo atributo" subtitle="Talla, color, material…" icon="tag"
            :crumbs="['Atributos' => route('attributes.index'), 'Nuevo' => null]">

    <form method="POST" action="{{ route('attributes.store') }}" novalidate>
        @csrf

        <x-mus.panel title="Datos del atributo" sub="Nombre y tipo de valor">
            @include('attributes.form', ['item' => null])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('attributes.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar atributo</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
