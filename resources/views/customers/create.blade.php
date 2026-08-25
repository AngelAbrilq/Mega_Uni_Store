<x-mus.page title="Nuevo cliente" subtitle="Datos de contacto e identificación" icon="users"
            :crumbs="['Clientes' => route('customers.index'), 'Nuevo' => null]">

    <form method="POST" enctype="multipart/form-data" action="{{ route('customers.store') }}" novalidate>
        @csrf

        <x-mus.panel title="Datos del cliente" sub="Nombre, contacto y documento">
            @include('customers.form', ['item' => null])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('customers.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cliente</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
