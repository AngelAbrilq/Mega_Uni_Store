<x-mus.page title="Nuevo permiso" subtitle="Una acción nueva que se puede asignar a los roles" icon="key"
            :crumbs="['Todos los negocios' => route('sistema.index'), 'Permisos' => route('sistema.permisos.index'), 'Nuevo' => null]">
    <form method="POST" action="{{ route('sistema.permisos.store') }}" novalidate>
        @csrf
        <x-mus.panel title="Datos del permiso" sub="Módulo, acción y para qué sirve">
            @include('sistema.permisos.form', ['permiso' => null])
            <x-slot name="foot">
                <x-mus.btn href="{{ route('sistema.permisos.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Crear permiso</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
