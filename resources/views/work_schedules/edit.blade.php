<x-mus.page title="Editar {{ $horario->name }}" subtitle="Nombre y estado" icon="clock"
            :crumbs="['Empleados' => route('employees.index'), 'Horarios' => route('work_schedules.index'), $horario->name => route('work_schedules.show', $horario), 'Editar' => null]">
    <form method="POST" action="{{ route('work_schedules.update', $horario) }}" novalidate>
        @csrf @method('PUT')
        <x-mus.panel title="Datos del horario">
            @include('work_schedules.form', ['horario' => $horario])
            <x-slot name="foot">
                <x-mus.btn href="{{ route('work_schedules.show', $horario) }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
