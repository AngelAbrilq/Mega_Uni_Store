<x-mus.page title="Nuevo cargo" subtitle="Un puesto del equipo" icon="shield"
            :crumbs="['Empleados' => route('employees.index'), 'Cargos' => route('positions.index'), 'Nuevo' => null]">
    <form method="POST" action="{{ route('positions.store') }}" novalidate>
        @csrf
        <x-mus.panel title="Datos del cargo">
            @include('positions.form', ['cargo' => null])
            <x-slot name="foot">
                <x-mus.btn href="{{ route('positions.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Crear cargo</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
