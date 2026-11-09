<x-mus.page title="Nuevo empleado" subtitle="Registro en el equipo del local" icon="users"
            :crumbs="['Empleados' => route('employees.index'), 'Nuevo' => null]">
    <form method="POST" action="{{ route('employees.store') }}" novalidate>
        @csrf
        <x-mus.panel title="Datos del empleado" sub="Identificación, ingreso y salario">
            @include('employees.form', ['empleado' => null])
            <x-slot name="foot">
                <x-mus.btn href="{{ route('employees.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Registrar empleado</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
