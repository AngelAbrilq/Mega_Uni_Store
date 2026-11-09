<x-mus.page title="Nuevo proveedor" subtitle="Datos comerciales y de contacto" icon="truck"
            :crumbs="['Proveedores' => route('suppliers.index'), 'Nuevo' => null]">

    <form method="POST" enctype="multipart/form-data" action="{{ route('suppliers.store') }}" novalidate>
        @csrf

        <x-mus.panel title="Datos del proveedor" sub="Razón social, identificación y contacto">
            @include('suppliers.form', ['item' => null])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('suppliers.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar proveedor</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
