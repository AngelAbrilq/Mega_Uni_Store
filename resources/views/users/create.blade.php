<x-mus.page title="Nuevo usuario" subtitle="Crea la cuenta y define qué puede hacer" icon="shield"
            :crumbs="['Usuarios' => route('users.index'), 'Nuevo' => null]">

    <form method="POST" enctype="multipart/form-data" action="{{ route('users.store') }}" novalidate>
        @csrf

        <x-mus.panel title="Datos de la cuenta" sub="Con estos datos inicia sesión en el panel">
            @include('users.form', ['item' => null])

            <x-slot name="foot">
                <x-mus.btn href="{{ route('users.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Crear usuario</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
