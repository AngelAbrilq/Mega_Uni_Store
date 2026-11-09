@php use App\Support\Formato; @endphp

<x-mus.page title="Empleados" subtitle="El equipo de este local" icon="users">
    <x-slot name="actions">
        @can('rrhh.configurar')
            <x-mus.btn href="{{ route('departments.index') }}" icon="layers">Áreas</x-mus.btn>
            <x-mus.btn href="{{ route('positions.index') }}" icon="shield">Cargos</x-mus.btn>
            <x-mus.btn href="{{ route('work_schedules.index') }}" icon="clock">Horarios</x-mus.btn>
        @endcan
        @can('empleados.crear')
            <x-mus.btn href="{{ route('employees.create') }}" variant="primary" icon="plus">Nuevo empleado</x-mus.btn>
        @endcan
    </x-slot>

    <x-mus.panel :pad="false" :reveal="true">
        <form method="GET" action="{{ route('employees.index') }}" class="emp-fil">
            <div class="emp-tabs">
                <a href="{{ route('employees.index', ['q' => $q, 'area' => $area ?: null]) }}" class="{{ $estado === 'activos' ? 'is-on' : '' }}">Activos <i>{{ $conteo['activos'] }}</i></a>
                <a href="{{ route('employees.index', ['estado' => 'inactivos', 'q' => $q]) }}" class="{{ $estado === 'inactivos' ? 'is-on' : '' }}">Retirados <i>{{ $conteo['inactivos'] }}</i></a>
            </div>
            <input type="hidden" name="estado" value="{{ $estado === 'inactivos' ? 'inactivos' : '' }}">
            @if ($areas->count())
                <select name="area" class="emp-sel" onchange="this.form.submit()" aria-label="Área">
                    <option value="">Todas las áreas</option>
                    @foreach ($areas as $id => $nombre)
                        <option value="{{ $id }}" @selected($area === $id)>{{ $nombre }}</option>
                    @endforeach
                </select>
            @endif
            <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por nombre, código o documento" class="emp-q" aria-label="Buscar">
        </form>

        @if ($empleados->total())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Empleado</th>
                            <th>Código</th>
                            <th>Cargo</th>
                            <th>Área</th>
                            <th>{{ $estado === 'activos' ? 'Ingreso' : 'Retiro' }}</th>
                            @can('empleados.editar')<th class="num">Salario</th>@endcan
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($empleados as $e)
                            @php $cargo = $e->cargoActual(); $areaP = $e->areaPrincipal(); @endphp
                            <tr data-row>
                                <td>
                                    <a href="{{ route('employees.show', $e) }}" class="emp-who">
                                        <span class="emp-av">{{ $e->iniciales }}</span>
                                        <span><b>{{ $e->nombre_completo }}</b>
                                            <small>{{ $e->user ? 'Usuario: ' . $e->user->email : 'Sin usuario del sistema' }}</small></span>
                                    </a>
                                </td>
                                <td><code class="emp-code">{{ $e->code }}</code></td>
                                <td>{{ $cargo?->name ?? '—' }}</td>
                                <td>
                                    {{ $areaP?->name ?? '—' }}
                                    @if ($e->departments->count() > 1)<span class="sub"> +{{ $e->departments->count() - 1 }}</span>@endif
                                </td>
                                <td>{{ $estado === 'activos' ? $e->hire_date?->format('d/m/Y') : $e->termination_date?->format('d/m/Y') }}</td>
                                @can('empleados.editar')<td class="num">{{ Formato::moneda($e->base_salary) }}</td>@endcan
                                <td class="act">
                                    <x-mus.btn href="{{ route('employees.show', $e) }}" variant="ghost" :sm="true" class="mb--icon" title="Ver ficha">
                                        <x-mus.icon name="eye" :w="15" />
                                    </x-mus.btn>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-mus.pagination :items="$empleados" label="empleados" />
        @else
            <x-mus.empty icon="users" :title="$q !== '' ? 'Ningún empleado coincide' : ($estado === 'activos' ? 'Todavía no hay empleados' : 'Nadie se ha retirado')"
                         text="Registra al equipo del local con su código, fecha de ingreso, salario, cargo, área y horario.">
                @if ($estado === 'activos' && $q === '')
                    @can('empleados.crear')
                        <x-slot name="action"><x-mus.btn href="{{ route('employees.create') }}" variant="primary" icon="plus">Nuevo empleado</x-mus.btn></x-slot>
                    @endcan
                @endif
            </x-mus.empty>
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        .emp-fil{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; padding:12px 14px; border-bottom:1px solid var(--line-2); background:var(--paper); }
        .emp-tabs{ display:flex; gap:4px; }
        .emp-tabs a{ padding:6px 12px; border-radius:999px; font-size:12.8px; font-weight:600; color:var(--ink-2); text-decoration:none; }
        .emp-tabs a i{ font-style:normal; opacity:.6; margin-left:3px; }
        .emp-tabs a.is-on{ background:var(--a-600); color:#fff; }
        .emp-tabs a.is-on i{ opacity:.85; }
        .emp-sel, .emp-q{ height:36px; border:1px solid var(--line); border-radius:9px; font:inherit; font-size:13px; padding:0 11px; background:#fff; color:var(--ink); }
        .emp-sel{ padding-right:30px; -webkit-appearance:none; appearance:none;
                  background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2366768F' stroke-width='2.4' stroke-linecap='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 11px center; }
        .emp-q{ flex:1 1 220px; margin-left:auto; max-width:340px; }
        .emp-who{ display:flex; gap:10px; align-items:center; color:inherit; text-decoration:none; }
        .emp-who b{ display:block; font-size:13.4px; color:var(--ink); }
        .emp-who small{ display:block; font-size:11.5px; color:var(--muted); }
        .emp-av{ width:34px; height:34px; flex:none; border-radius:50%; display:grid; place-items:center; font-size:12px; font-weight:700;
                 background:rgba(46,110,168,.12); color:var(--a-700, #1f4f7a); }
        .emp-code{ font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12px; font-weight:600; color:var(--ink-2); }
        @media (max-width:760px){ .emp-q{ max-width:none; margin-left:0; flex-basis:100%; height:42px; font-size:16px; } }
    </style>
    @endpush
</x-mus.page>
