@php
    use App\Support\Formato;

    $colores = [
        'Superadministrador' => '#1B4870',
        'Administrador'      => '#2E6EA8',
        'Supervisor'         => '#4A8FC9',
        'Vendedor'           => '#3E7D5C',
        'Bodeguero'          => '#96703C',
        'Reportero'          => '#66768F',
    ];
@endphp

<x-mus.page title="Usuarios" subtitle="Quién entra al sistema y con qué permisos" icon="shield"
            :crumbs="['Administración' => null, 'Usuarios' => null]">
    <x-slot name="actions">
        @can('usuarios.crear')
            <x-mus.btn href="{{ route('users.create') }}" variant="primary" icon="plus"
                       data-modal="Nuevo usuario" data-modal-ancho="820">
                Nuevo usuario
            </x-mus.btn>
        @endcan
    </x-slot>

    <x-mus.panel :pad="false" :reveal="true">

        <form method="GET" action="{{ route('users.index') }}" class="ufil">
            <div class="ufil__search">
                <x-mus.icon name="search" :w="15" stroke-width="2" />
                <input type="text" name="q" value="{{ $q }}" placeholder="Buscar por nombre o correo…">
            </div>

            <select name="rol" class="ufil__sel" onchange="this.form.submit()">
                <option value="">Todos los roles</option>
                @foreach ($roles as $r)
                    <option value="{{ $r }}" @selected($rol === $r)>{{ $r }}</option>
                @endforeach
            </select>

            <button type="submit" class="mb mb--primary mb--sm">Buscar</button>

            @if ($q !== '' || $rol !== '')
                <a href="{{ route('users.index') }}" class="mb mb--ghost mb--sm">Limpiar</a>
            @endif

            <span class="ufil__sp"></span>
            <span class="mtool__count">{{ number_format($users->total(), 0, ',', '.') }} usuarios</span>
        </form>

        @if ($users->total())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Usuario</th>
                            <th>Roles</th>
                            <th>Alta</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr data-row>
                                <td><span class="mt__id">{{ $user->id }}</span></td>
                                <td>
                                    <div class="mt__ent">
                                        <x-mus.avatar :src="$user->imagen" :letras="$user->iniciales"
                                                      :color="$colores[$user->roles->first()?->name] ?? '#22334C'"
                                                      :size="34" :round="true" />
                                        <span>
                                            <b>{{ $user->name }}</b>
                                            <span class="sub">{{ $user->email }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @forelse ($user->roles as $r)
                                        <x-mus.badge tone="soft">{{ $r->name }}</x-mus.badge>
                                    @empty
                                        <x-mus.badge tone="bad" :dot="true">Sin rol</x-mus.badge>
                                    @endforelse
                                </td>
                                <td>{{ Formato::enPalabras($user->created_at, 'D MMM YYYY', '—') }}</td>
                                <td class="act">
                                    <span class="mt__acts">
                                        @can('usuarios.ver')
                                            <x-mus.btn href="{{ route('users.show', $user) }}"
                                                       variant="ghost" :sm="true" class="mb--icon" title="Ver">
                                                <x-mus.icon name="eye" :w="15" />
                                            </x-mus.btn>
                                        @endcan
                                        @can('usuarios.editar')
                                            <x-mus.btn href="{{ route('users.edit', $user) }}"
                                                       variant="ghost" :sm="true" class="mb--icon" title="Editar">
                                                <x-mus.icon name="pencil" :w="15" />
                                            </x-mus.btn>
                                        @endcan
                                        @can('usuarios.eliminar')
                                            @if ($user->id !== auth()->id())
                                                <x-mus.del :action="route('users.destroy', $user)"
                                                           what="el usuario {{ $user->name }}" />
                                            @endif
                                        @endcan
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$users" label="usuarios" />
        @else
            <x-mus.empty icon="shield" title="Ningún usuario coincide"
                         text="Prueba con otro nombre, otro correo o quita el filtro de rol." />
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        .ufil{ display:flex; align-items:center; gap:9px; flex-wrap:wrap;
               padding:13px 16px; border-bottom:1px solid var(--line-2); background:var(--paper); }
        .ufil__search{ position:relative; display:flex; align-items:center; gap:8px; flex:1 1 250px;
                       min-width:210px; padding:0 12px; height:36px; background:#fff;
                       border:1px solid var(--line); border-radius:9px; color:var(--muted);
                       transition:border-color .18s var(--e-soft), box-shadow .18s var(--e-soft); }
        .ufil__search:focus-within{ border-color:var(--a-400); box-shadow:0 0 0 3px rgba(46,110,168,.12); }
        .ufil__search input{ flex:1; border:0; outline:0; background:transparent;
                             font:inherit; font-size:13px; color:var(--ink); }
        .ufil__sel{ height:36px; padding:0 30px 0 11px; border:1px solid var(--line); border-radius:9px;
                    background:#fff; font:inherit; font-size:13px; color:var(--ink); cursor:pointer; }
        .ufil__sp{ flex:1 1 auto; }

        /* ── Celular: un filtro por línea, campos grandes ── */
        @media (max-width:760px){
            .ufil{ gap:8px; }
            .ufil__search{ flex:1 0 100%; min-width:0; height:42px; }
            .ufil__search input{ font-size:16px; }
            .ufil__sel{ flex:1 0 100%; width:100%; min-width:0; height:42px; font-size:16px; }
            .ufil__sp{ display:none; }
            .ufil .mb{ flex:1 1 auto; }
        }
    </style>
    @endpush
</x-mus.page>
