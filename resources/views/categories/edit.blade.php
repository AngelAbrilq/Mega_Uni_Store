<x-mus.page title="Editar categoría" subtitle="{{ $category->name }}" icon="layers"
            :crumbs="['Categorías' => route('categories.index'), 'Editar' => null]">

    <x-slot name="actions">
        <x-mus.btn href="{{ route('categories.show', $category) }}" icon="eye">Ver detalle</x-mus.btn>
    </x-slot>

    <form method="POST" enctype="multipart/form-data" action="{{ route('categories.update', $category) }}" novalidate>
        @csrf
        @method('PUT')

        <x-mus.panel title="Datos de la categoría" sub="Nombre, jerarquía y estado">
            @include('categories.form', ['item' => $category])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('categories.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cambios</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
