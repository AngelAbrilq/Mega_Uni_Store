<x-mus.page title="Editar {{ $area->name }}" subtitle="Nombre, responsable y estado" icon="layers"
            :crumbs="['Empleados' => route('employees.index'), 'Áreas' => route('departments.index'), $area->name => route('departments.show', $area), 'Editar' => null]">
    <form method="POST" action="{{ route('departments.update', $area) }}" novalidate>
        @csrf @method('PUT')
        <x-mus.panel title="Datos del área">
            @include('departments.form', ['area' => $area])
            <x-slot name="foot">
                <x-mus.btn href="{{ route('departments.show', $area) }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
