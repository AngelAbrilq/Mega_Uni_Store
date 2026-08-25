<x-mus.page title="Editar producto" subtitle="{{ $product->name }}" icon="box"
            :crumbs="['Productos' => route('products.index'), 'Editar' => null]">

    <x-slot name="actions">
        <x-mus.btn href="{{ route('products.show', $product) }}" icon="eye">Ver detalle</x-mus.btn>
    </x-slot>

    <form method="POST" enctype="multipart/form-data" action="{{ route('products.update', $product) }}" novalidate>
        @csrf
        @method('PUT')

        <x-mus.panel title="Datos del producto" sub="Identificación, clasificación y precios">
            @include('products.form', ['item' => $product])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('products.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cambios</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
