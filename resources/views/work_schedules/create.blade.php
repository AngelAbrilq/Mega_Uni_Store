<x-mus.page title="Nuevo horario" subtitle="Después le agregas las franjas de cada día" icon="clock"
            :crumbs="['Empleados' => route('employees.index'), 'Horarios' => route('work_schedules.index'), 'Nuevo' => null]">
    <form method="POST" action="{{ route('work_schedules.store') }}" novalidate>
        @csrf
        <x-mus.panel title="Datos del horario">
            @include('work_schedules.form', ['horario' => null])
            <x-slot name="foot">
                <x-mus.btn href="{{ route('work_schedules.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Crear horario</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
