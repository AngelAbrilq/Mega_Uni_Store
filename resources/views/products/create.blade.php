<x-mus.page title="Nuevo producto" subtitle="Ficha completa del artículo" icon="box"
            :crumbs="['Productos' => route('products.index'), 'Nuevo' => null]">

    <form method="POST" enctype="multipart/form-data" action="{{ route('products.store') }}" novalidate>
        @csrf

        <x-mus.panel title="Datos del producto" sub="Identificación, clasificación y precios">
            @include('products.form', ['item' => null])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('products.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar producto</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
