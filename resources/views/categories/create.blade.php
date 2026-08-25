<x-mus.page title="Nueva categoría" subtitle="Agrupa productos que se parecen" icon="layers"
            :crumbs="['Categorías' => route('categories.index'), 'Nuevo' => null]">

    <form method="POST" enctype="multipart/form-data" action="{{ route('categories.store') }}" novalidate>
        @csrf

        <x-mus.panel title="Datos de la categoría" sub="Nombre, jerarquía y estado">
            @include('categories.form', ['item' => null])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('categories.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar categoría</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
