<x-mus.page title="Nuevo rol" subtitle="Un cargo que solo existe en este negocio" icon="shield"
            :crumbs="['Roles' => route('roles.index'), 'Nuevo' => null]">

    <form method="POST" action="{{ route('roles.store') }}" novalidate>
        @csrf

        <x-mus.panel title="Datos del rol" sub="Nombre y permisos">
            @include('roles.form', ['item' => null])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('roles.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar rol</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
