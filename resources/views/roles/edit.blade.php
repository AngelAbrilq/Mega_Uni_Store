<x-mus.page title="Editar rol" subtitle="{{ $role->name }}" icon="shield"
            :crumbs="['Roles' => route('roles.index'), 'Editar' => null]">

    <form method="POST" action="{{ route('roles.update', $role) }}" novalidate>
        @csrf
        @method('PUT')

        <x-mus.panel title="Datos del rol" sub="Nombre y permisos">
            @include('roles.form', ['item' => $role])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('roles.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cambios</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
