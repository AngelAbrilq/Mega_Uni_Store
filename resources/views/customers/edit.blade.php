<x-mus.page title="Editar cliente" subtitle="{{ trim($customer->first_name . ' ' . $customer->last_name) }}" icon="users"
            :crumbs="['Clientes' => route('customers.index'), 'Editar' => null]">

    <x-slot name="actions">
        <x-mus.btn href="{{ route('customers.show', $customer) }}" icon="eye">Ver detalle</x-mus.btn>
    </x-slot>

    <form method="POST" enctype="multipart/form-data" action="{{ route('customers.update', $customer) }}" novalidate>
        @csrf
        @method('PUT')

        <x-mus.panel title="Datos del cliente" sub="Nombre, contacto y documento">
            @include('customers.form', ['item' => $customer])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('customers.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cambios</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
