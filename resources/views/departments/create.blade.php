<x-mus.page title="Nueva área" subtitle="Una parte del equipo del local" icon="layers"
            :crumbs="['Empleados' => route('employees.index'), 'Áreas' => route('departments.index'), 'Nueva' => null]">
    <form method="POST" action="{{ route('departments.store') }}" novalidate>
        @csrf
        <x-mus.panel title="Datos del área">
            @include('departments.form', ['area' => null])
            <x-slot name="foot">
                <x-mus.btn href="{{ route('departments.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Crear área</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
