<x-mus.page title="Editar usuario" subtitle="{{ $user->name }}" icon="shield"
            :crumbs="['Usuarios' => route('users.index'), 'Editar' => null]">

    <x-slot name="actions">
        <x-mus.btn href="{{ route('users.show', $user) }}" icon="eye">Ver detalle</x-mus.btn>
    </x-slot>

    <form method="POST" enctype="multipart/form-data" action="{{ route('users.update', $user) }}" novalidate>
        @csrf
        @method('PUT')

        <x-mus.panel title="Datos de la cuenta" sub="Deja la contraseña vacía si no quieres cambiarla">
            @include('users.form', ['item' => $user])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('users.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cambios</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
