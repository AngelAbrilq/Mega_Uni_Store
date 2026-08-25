@php
    /* Agrupa los permisos del usuario por módulo para leerlos de un vistazo. */
    $porModulo = [];
    foreach ($user->getAllPermissions() as $p) {
        $partes = explode('.', $p->name);
        $modulo = $partes[0];
        $accion = $partes[1] ?? 'ver';
        $porModulo[$modulo][] = $accion;
    }
    ksort($porModulo);

    $etiquetas = [
        'productos' => 'Productos', 'categorias' => 'Categorías', 'clientes' => 'Clientes',
        'proveedores' => 'Proveedores', 'unidades' => 'Unidades', 'impuestos' => 'Impuestos',
        'medios_pago' => 'Medios de pago', 'atributos' => 'Atributos', 'usuarios' => 'Usuarios',
        'panel' => 'Panel', 'reportes' => 'Reportes', 'inventario' => 'Inventario',
        'roles' => 'Roles', 'configuracion' => 'Configuración',
    ];
@endphp

<x-mus.page title="{{ $user->name }}" subtitle="Ficha de usuario" icon="shield"
            :crumbs="['Usuarios' => route('users.index'), 'Detalle' => null]">

    <x-slot name="actions">
        @can('usuarios.editar')
            <x-mus.btn href="{{ route('users.edit', $user) }}" icon="pencil">Editar</x-mus.btn>
        @endcan
    </x-slot>

    <div class="ushow">
        <x-mus.panel title="Cuenta" sub="Datos de acceso">
            <div class="mficha">
                <x-mus.avatar :src="$user->imagen" :letras="$user->iniciales"
                              :color="$user->color_avatar" :size="64" :round="true" />
                <div class="mficha__t">
                    <b>{{ $user->name }}</b>
                    <span>{{ $user->roles->pluck('name')->join(' · ') ?: 'Sin rol' }}</span>
                </div>
            </div>

            <dl class="mdl">
                <div><dt>Nombre</dt><dd>{{ $user->name }}</dd></div>
                <div><dt>Correo</dt><dd>{{ $user->email }}</dd></div>
                <div><dt>Identificador</dt><dd>#{{ $user->id }}</dd></div>
                <div>
                    <dt>Correo verificado</dt>
                    <dd>{{ $user->email_verified_at ? 'Sí' : 'No' }}</dd>
                </div>
                <div>
                    <dt>Alta</dt>
                    <dd>{{ $user->created_at?->locale('es')->isoFormat('D [de] MMMM, YYYY') ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Roles</dt>
                    <dd>
                        @forelse ($user->roles as $r)
                            <x-mus.badge tone="soft">{{ $r->name }}</x-mus.badge>
                        @empty
                            <x-mus.badge tone="bad" :dot="true">Sin rol</x-mus.badge>
                        @endforelse
                    </dd>
                </div>
            </dl>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('users.index') }}" icon="back" :block="true">
                    Volver al listado
                </x-mus.btn>
            </x-slot>
        </x-mus.panel>

        <x-mus.panel title="Permisos efectivos"
                     sub="{{ $user->getAllPermissions()->count() }} permisos heredados de sus roles">
            @if (count($porModulo))
                <div class="uperm">
                    @foreach ($porModulo as $modulo => $acciones)
                        <div class="uperm__row">
                            <span class="uperm__mod">{{ $etiquetas[$modulo] ?? ucfirst($modulo) }}</span>
                            <span class="uperm__acts">
                                @foreach (['ver', 'crear', 'editar', 'eliminar'] as $a)
                                    <i class="{{ in_array($a, $acciones, true) ? 'on' : '' }}">{{ $a }}</i>
                                @endforeach
                                @foreach (array_diff($acciones, ['ver', 'crear', 'editar', 'eliminar']) as $extra)
                                    <i class="on">{{ $extra }}</i>
                                @endforeach
                            </span>
                        </div>
                    @endforeach
                </div>
            @else
                <x-mus.empty icon="key" title="Sin permisos"
                             text="Este usuario no tiene ningún rol asignado, así que no puede entrar a ningún módulo." />
            @endif
        </x-mus.panel>
    </div>

    @push('styles')
    <style>
        .ushow{ display:grid; gap:16px; grid-template-columns:minmax(0,1fr) minmax(0,1.35fr); align-items:start; }
        @media (max-width:960px){ .ushow{ grid-template-columns:minmax(0,1fr); } }

        .uperm__row{ display:flex; align-items:center; gap:12px; justify-content:space-between;
                     padding:9px 0; border-bottom:1px solid var(--line-2); }
        .uperm__row:last-child{ border-bottom:0; }
        .uperm__mod{ font-size:13px; font-weight:600; color:var(--ink); }
        .uperm__acts{ display:flex; gap:5px; flex-wrap:wrap; }
        .uperm__acts i{ font-style:normal; font-size:10.6px; letter-spacing:.05em; text-transform:uppercase;
                        padding:3px 7px; border-radius:5px; font-weight:700;
                        background:var(--line-2); color:var(--muted-2); }
        .uperm__acts i.on{ background:rgba(46,110,168,.12); color:var(--a-600); }
    </style>
    @endpush
</x-mus.page>
