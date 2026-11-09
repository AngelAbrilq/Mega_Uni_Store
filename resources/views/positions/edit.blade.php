<x-mus.page title="Editar {{ $cargo->name }}" subtitle="Nombre, nivel y estado" icon="shield"
            :crumbs="['Empleados' => route('employees.index'), 'Cargos' => route('positions.index'), 'Editar' => null]">
    <form method="POST" action="{{ route('positions.update', $cargo) }}" novalidate>
        @csrf @method('PUT')
        <x-mus.panel title="Datos del cargo">
            @include('positions.form', ['cargo' => $cargo])
            <x-slot name="foot">
                <x-mus.btn href="{{ route('positions.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
