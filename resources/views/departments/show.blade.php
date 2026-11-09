<x-mus.page :title="$area->name" :subtitle="$area->manager ? 'Responsable: ' . $area->manager->nombre_completo : 'Sin responsable'" icon="layers"
            :crumbs="['Empleados' => route('employees.index'), 'Áreas' => route('departments.index'), $area->name => null]">
    <x-slot name="actions">
        <x-mus.btn href="{{ route('departments.edit', $area) }}" icon="pencil">Editar</x-mus.btn>
        <x-mus.del :action="route('departments.destroy', $area)" what="el área {{ $area->name }}" />
    </x-slot>

    @error('area')<div class="rh-err"><x-mus.icon name="alert" :w="15" /> {{ $message }}</div>@enderror

    <x-mus.panel title="Empleados del área" :sub="$area->employees->count() . ' ' . ($area->employees->count() === 1 ? 'persona' : 'personas')" :pad="false">
        @if ($area->employees->count())
            <div class="mt-wrap">
                <table class="mt">
                    <thead><tr><th>Empleado</th><th>Cargo</th><th>En el área desde</th><th>Tipo</th></tr></thead>
                    <tbody>
                        @foreach ($area->employees as $e)
                            <tr data-row>
                                <td><a href="{{ route('employees.show', $e) }}"><b>{{ $e->nombre_completo }}</b></a> <span class="sub">{{ $e->code }}</span>
                                    @if ($area->manager_id === $e->id)<x-mus.badge tone="ok">Responsable</x-mus.badge>@endif</td>
                                <td>{{ $e->cargoActual()?->name ?? '—' }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($e->pivot->assigned_at)->format('d/m/Y') }}</td>
                                <td>@if ($e->pivot->is_primary)<x-mus.badge tone="info">Principal</x-mus.badge>@else <span class="sub">Secundaria</span> @endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-mus.empty icon="users" title="Nadie en esta área" text="Asigna empleados desde su ficha, en la sección Áreas." />
        @endif
    </x-mus.panel>
    @include('departments._estilos')
</x-mus.page>
