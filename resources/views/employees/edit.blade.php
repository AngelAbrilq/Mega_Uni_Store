<x-mus.page title="Editar {{ $empleado->nombre_completo }}" subtitle="Datos personales, ingreso y salario" icon="users"
            :crumbs="['Empleados' => route('employees.index'), $empleado->nombre_completo => route('employees.show', $empleado), 'Editar' => null]">
    <form method="POST" action="{{ route('employees.update', $empleado) }}" novalidate>
        @csrf @method('PUT')
        <x-mus.panel title="Datos del empleado" sub="Cargo, áreas y horario se cambian desde la ficha">
            @include('employees.form', ['empleado' => $empleado])
            <x-slot name="foot">
                <x-mus.btn href="{{ route('employees.show', $empleado) }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cambios</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
