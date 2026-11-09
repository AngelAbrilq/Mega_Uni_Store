<x-mus.page title="Roles" subtitle="Qué puede hacer cada persona en este negocio" icon="shield">
    <x-slot name="actions">
        <x-mus.btn href="{{ route('roles.create') }}" variant="primary" icon="plus">
            Nuevo rol
        </x-mus.btn>
    </x-slot>

    <x-mus.panel :pad="false" :reveal="true">
        <div class="mt-wrap">
            <table class="mt">
                <thead>
                    <tr>
                        <th>Rol</th>
                        <th>Origen</th>
                        <th>Permisos</th>
                        <th>Personas</th>
                        <th class="act"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr data-row>
                            <td><b>{{ $role->name }}</b></td>
                            <td>
                                @if ($role->esDelSistema())
                                    <x-mus.badge tone="off">Del sistema</x-mus.badge>
                                @else
                                    <x-mus.badge tone="info" :dot="true">Tuyo</x-mus.badge>
                                @endif
                            </td>
                            <td>{{ $role->permissions_count }}</td>
                            <td>{{ $role->users_count }}</td>
                            <td class="act">
                                <span class="mt__acts">
                                    @if ($role->esEditable())
                                        <x-mus.btn href="{{ route('roles.edit', $role) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Editar">
                                            <x-mus.icon name="pencil" :w="15" />
                                        </x-mus.btn>
                                        <x-mus.del :action="route('roles.destroy', $role)" what="el rol" />
                                    @else
                                        <span class="mrol__fijo" title="Los roles del sistema no se modifican">
                                            <x-mus.icon name="shield" :w="15" />
                                        </span>
                                    @endif
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-mus.panel>

    <p class="mrol__nota">
        Los roles del sistema sirven en cualquier negocio y no se modifican: si un
        controlador dejara de encontrar «Superadministrador» por su nombre, nadie
        podría entrar. Para lo que este negocio necesita distinto —un «Estilista»,
        un «Bodeguero de patio»— crea el tuyo: solo existe aquí.
    </p>

    @push('styles')
    <style>
        .mrol__fijo{ display:inline-grid; place-items:center; width:30px; height:30px;
                     color:var(--muted-2); }
        .mrol__nota{ margin-top:14px; max-width:66ch; font-size:12.4px; line-height:1.6;
                     color:var(--muted); }
    </style>
    @endpush
</x-mus.page>
