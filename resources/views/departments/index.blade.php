<x-mus.page title="Áreas" subtitle="Cómo se organiza el equipo del local" icon="layers"
            :crumbs="['Empleados' => route('employees.index'), 'Áreas' => null]">
    <x-slot name="actions">
        <x-mus.btn href="{{ route('departments.create') }}" variant="primary" icon="plus">Nueva área</x-mus.btn>
    </x-slot>

    @error('area')<div class="rh-err"><x-mus.icon name="alert" :w="15" /> {{ $message }}</div>@enderror

    @if ($areas->count())
        <div class="rh-cards">
            @foreach ($areas as $a)
                <a href="{{ route('departments.show', $a) }}" class="rh-card {{ $a->is_active ? '' : 'is-off' }}">
                    <span class="rh-card__ico"><x-mus.icon name="layers" :w="18" /></span>
                    <b>{{ $a->name }}</b>
                    <span class="rh-card__sub">{{ $a->description ?: 'Sin descripción' }}</span>
                    <span class="rh-card__foot">
                        <span><x-mus.icon name="users" :w="13" /> {{ $a->employees_count }} {{ $a->employees_count === 1 ? 'persona' : 'personas' }}</span>
                        <span>{{ $a->manager ? 'Resp.: ' . $a->manager->nombre_completo : 'Sin responsable' }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    @else
        <x-mus.panel>
            <x-mus.empty icon="layers" title="Todavía no hay áreas" text="Crea Ventas, Caja, Bodega o Administración y asígnales un responsable.">
                <x-slot name="action"><x-mus.btn href="{{ route('departments.create') }}" variant="primary" icon="plus">Nueva área</x-mus.btn></x-slot>
            </x-mus.empty>
        </x-mus.panel>
    @endif
    @include('departments._estilos')
</x-mus.page>
