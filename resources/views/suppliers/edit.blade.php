<x-mus.page title="Editar proveedor" subtitle="{{ $supplier->name }}" icon="truck"
            :crumbs="['Proveedores' => route('suppliers.index'), 'Editar' => null]">

    <x-slot name="actions">
        <x-mus.btn href="{{ route('suppliers.show', $supplier) }}" icon="eye">Ver detalle</x-mus.btn>
    </x-slot>

    <form method="POST" enctype="multipart/form-data" action="{{ route('suppliers.update', $supplier) }}" novalidate>
        @csrf
        @method('PUT')

        <x-mus.panel title="Datos del proveedor" sub="Razón social, identificación y contacto">
            @include('suppliers.form', ['item' => $supplier])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('suppliers.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cambios</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
