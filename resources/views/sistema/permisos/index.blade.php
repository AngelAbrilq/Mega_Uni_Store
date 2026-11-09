@php use App\Support\CatalogoPermisos; @endphp

<x-mus.page title="Permisos" subtitle="Qué acciones existen en el sistema y quién las tiene" icon="key"
            :crumbs="['Todos los negocios' => route('sistema.index'), 'Permisos' => null]">
    <x-slot name="actions">
        <x-mus.btn href="{{ route('roles.index') }}" icon="shield">Roles</x-mus.btn>
        <x-mus.btn href="{{ route('sistema.permisos.create') }}" variant="primary" icon="plus">Nuevo permiso</x-mus.btn>
    </x-slot>

    @error('permiso')
        <div class="prm-err"><x-mus.icon name="alert" :w="15" /> {{ $message }}</div>
    @enderror

    <div class="prm-tiras">
        <div><span>Permisos</span><b>{{ $total }}</b></div>
        <div><span>Del sistema</span><b>{{ $total - $propios }}</b></div>
        <div><span>Personalizados</span><b>{{ $propios }}</b></div>
        <div><span>Módulos</span><b>{{ $modulos->count() }}</b></div>
    </div>

    <x-mus.panel :pad="false">
        <form method="GET" action="{{ route('sistema.permisos.index') }}" class="prm-filtro">
            <label>
                <span>Módulo</span>
                <select name="modulo" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    @foreach ($modulos as $m)
                        <option value="{{ $m }}" @selected($modulo === $m)>{{ CatalogoPermisos::modulo($m) }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Permisos del rol</span>
                <select name="rol" onchange="this.form.submit()">
                    <option value="">Cualquiera</option>
                    @foreach ($roles as $r)
                        <option value="{{ $r->id }}" @selected($rol?->id === $r->id)>{{ $r->name }}</option>
                    @endforeach
                </select>
            </label>
            @if ($modulo !== '' || $rol)
                <a href="{{ route('sistema.permisos.index') }}" class="prm-limpiar">Quitar filtros</a>
            @endif
        </form>

        @if ($rol)
            <p class="prm-rol"><x-mus.icon name="shield" :w="14" /> <b>{{ $rol->name }}</b> tiene
                {{ $permisos->flatten()->count() }} permisos en {{ $permisos->count() }} módulos.</p>
        @endif

        @if ($permisos->count())
            <div class="mt-wrap">
                <table class="mt prm-t">
                    <colgroup><col style="width:30%"><col style="width:22%"><col style="width:13%"><col style="width:14%"><col style="width:9%"><col style="width:12%"></colgroup>
                    <thead>
                        <tr>
                            <th>Permiso</th>
                            <th>Acción</th>
                            <th>Tipo</th>
                            <th>Origen</th>
                            <th class="num">Roles</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    @foreach ($permisos as $mod => $lista)
                        <tbody>
                            <tr class="prm-gr"><td colspan="6">{{ CatalogoPermisos::modulo($mod) }} <small>{{ $lista->count() }}</small></td></tr>
                            @foreach ($lista as $p)
                                <tr data-row>
                                    <td>
                                        <code class="prm-code">{{ $p->name }}</code>
                                        @if ($p->description)<span class="sub" style="display:block">{{ $p->description }}</span>@endif
                                    </td>
                                    <td>{{ $p->etiqueta() }}</td>
                                    <td><span class="prm-tipo is-{{ $p->tipo() }}">{{ CatalogoPermisos::TIPOS[$p->tipo()] }}</span></td>
                                    <td>
                                        @if ($p->is_system)
                                            <x-mus.badge tone="info">Sistema</x-mus.badge>
                                        @else
                                            <x-mus.badge tone="warn">Personalizado</x-mus.badge>
                                        @endif
                                    </td>
                                    <td class="num">{{ $p->roles_count }}</td>
                                    <td class="act">
                                        <span class="mt__acts">
                                            <x-mus.btn href="{{ route('sistema.permisos.edit', $p) }}" variant="ghost" :sm="true" class="mb--icon" title="Editar">
                                                <x-mus.icon name="pencil" :w="15" />
                                            </x-mus.btn>
                                            @unless ($p->is_system)
                                                <x-mus.del :action="route('sistema.permisos.destroy', $p)" what="el permiso {{ $p->name }}" />
                                            @endunless
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
        @else
            <x-mus.empty icon="key" title="Ningún permiso coincide" text="Quita los filtros para ver el catálogo completo." />
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        .prm-err{ display:flex; gap:8px; align-items:center; margin-bottom:14px; padding:10px 14px; border-radius:10px;
                  background:rgba(220,38,38,.08); color:#b91c1c; font-size:13px; font-weight:600; }
        .prm-tiras{ display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; margin-bottom:16px; }
        .prm-tiras div{ padding:14px 16px; border-radius:14px; background:var(--card); border:1px solid var(--line); }
        .prm-tiras span{ display:block; font-size:12px; color:var(--muted); }
        .prm-tiras b{ font-size:22px; letter-spacing:-.02em; font-variant-numeric:tabular-nums; }
        @media (max-width:700px){ .prm-tiras{ grid-template-columns:repeat(2, minmax(0,1fr)); } }
        .prm-filtro{ display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; padding:14px 16px; border-bottom:1px solid var(--line-2); }
        .prm-filtro label{ display:flex; flex-direction:column; gap:4px; font-size:11.5px; color:var(--muted); font-weight:600; }
        .prm-filtro select{ min-width:190px; padding:8px 30px 8px 10px; border:1px solid var(--line); border-radius:9px; background:var(--card); font-size:13px; color:var(--ink); }
        .prm-limpiar{ font-size:12.5px; padding-bottom:9px; }
        .prm-rol{ display:flex; gap:6px; align-items:center; margin:0; padding:10px 16px; font-size:13px; background:rgba(46,110,168,.06); border-bottom:1px solid var(--line-2); }
        .prm-t{ table-layout:fixed; min-width:760px; }
        .prm-t .prm-gr td{ padding:16px 16px 6px; font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase;
                           color:var(--muted); background:transparent; border-bottom:1px solid var(--line-2); }
        .prm-t .prm-gr small{ margin-left:6px; font-weight:600; opacity:.8; }
        .prm-filtro select{ appearance:none; -webkit-appearance:none;
            background:var(--card) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.4' stroke-linecap='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 10px center; }
        .prm-code{ font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12.5px; font-weight:600; color:var(--ink); }
        .prm-tipo{ display:inline-block; padding:2px 8px; border-radius:999px; font-size:11.5px; font-weight:600; }
        .prm-tipo.is-lectura{ background:rgba(23,163,74,.1); color:#15803d; }
        .prm-tipo.is-escritura{ background:rgba(46,110,168,.1); color:#1f4f7a; }
        .prm-tipo.is-exportacion{ background:rgba(245,165,36,.14); color:#a16207; }
    </style>
    @endpush
</x-mus.page>
