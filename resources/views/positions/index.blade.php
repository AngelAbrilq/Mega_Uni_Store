<x-mus.page title="Cargos" subtitle="La jerarquía del equipo: 1 es el nivel más alto" icon="shield"
            :crumbs="['Empleados' => route('employees.index'), 'Cargos' => null]">
    <x-slot name="actions">
        <x-mus.btn href="{{ route('positions.create') }}" variant="primary" icon="plus">Nuevo cargo</x-mus.btn>
    </x-slot>

    <x-mus.panel :pad="false" :reveal="true">
        @if ($cargos->count())
            <div class="mt-wrap">
                <table class="mt">
                    <thead><tr><th>Nivel</th><th>Cargo</th><th class="num">Personas</th><th>Estado</th><th class="act"></th></tr></thead>
                    <tbody>
                        @foreach ($cargos as $c)
                            <tr data-row>
                                <td><span class="pos-lvl {{ $c->level <= 3 ? 'is-alto' : '' }}" style="--n:{{ $c->level }}">{{ $c->level }}</span></td>
                                <td><b>{{ $c->name }}</b>@if ($c->description)<span class="sub" style="display:block">{{ $c->description }}</span>@endif</td>
                                <td class="num">{{ $c->activos_count }}</td>
                                <td><x-mus.badge :tone="$c->is_active ? 'ok' : 'off'" :dot="true">{{ $c->is_active ? 'Activo' : 'Inactivo' }}</x-mus.badge></td>
                                <td class="act"><span class="mt__acts">
                                    <x-mus.btn href="{{ route('positions.edit', $c) }}" variant="ghost" :sm="true" class="mb--icon" title="Editar"><x-mus.icon name="pencil" :w="15" /></x-mus.btn>
                                    <x-mus.del :action="route('positions.destroy', $c)" what="el cargo {{ $c->name }}" />
                                </span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-mus.empty icon="shield" title="Todavía no hay cargos" text="Crea Gerente (nivel 1), Supervisor (2), Vendedor, Cajero, Bodeguero…">
                <x-slot name="action"><x-mus.btn href="{{ route('positions.create') }}" variant="primary" icon="plus">Nuevo cargo</x-mus.btn></x-slot>
            </x-mus.empty>
        @endif
    </x-mus.panel>
    @push('styles')
    <style>
        .pos-lvl{ display:inline-grid; place-items:center; width:28px; height:28px; border-radius:8px; font-weight:700; font-size:13px;
                  background:color-mix(in srgb, var(--a-600, #2e6ea8) calc((11 - var(--n)) * 9%), transparent); color:var(--ink); }
        .pos-lvl.is-alto{ color:#fff; }
    </style>
    @endpush
</x-mus.page>
