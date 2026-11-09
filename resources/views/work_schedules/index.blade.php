<x-mus.page title="Horarios" subtitle="Las jornadas del local y quién las cumple" icon="clock"
            :crumbs="['Empleados' => route('employees.index'), 'Horarios' => null]">
    <x-slot name="actions">
        <x-mus.btn href="{{ route('work_schedules.create') }}" variant="primary" icon="plus">Nuevo horario</x-mus.btn>
    </x-slot>

    @error('horario')<div class="rh-err"><x-mus.icon name="alert" :w="15" /> {{ $message }}</div>@enderror

    @if ($horarios->count())
        <div class="hor-list">
            @foreach ($horarios as $h)
                <x-mus.panel :title="$h->name" :sub="$h->horasSemana() . ' h por semana · ' . $h->asignaciones_vigentes_count . ' ' . ($h->asignaciones_vigentes_count === 1 ? 'empleado' : 'empleados') . ($h->is_active ? '' : ' · inactivo')"
                             class="{{ $h->is_active ? '' : 'hor-off' }}">
                    <x-slot name="tools">
                        <x-mus.btn href="{{ route('work_schedules.show', $h) }}" :sm="true" icon="eye">Ver y editar franjas</x-mus.btn>
                    </x-slot>
                    @if ($h->shifts->count())
                        @include('work_schedules._semana', ['franjas' => $h->shifts])
                    @else
                        <p class="sub" style="margin:0">Sin franjas todavía.</p>
                    @endif
                </x-mus.panel>
            @endforeach
        </div>
    @else
        <x-mus.panel>
            <x-mus.empty icon="clock" title="Todavía no hay horarios" text="Crea «Turno mañana» o «Turno tarde» y agrégale las franjas de cada día.">
                <x-slot name="action"><x-mus.btn href="{{ route('work_schedules.create') }}" variant="primary" icon="plus">Nuevo horario</x-mus.btn></x-slot>
            </x-mus.empty>
        </x-mus.panel>
    @endif
    @include('departments._estilos')
    @push('styles')<style> .hor-list{ display:grid; gap:14px; } .hor-off{ opacity:.6; } </style>@endpush
</x-mus.page>
