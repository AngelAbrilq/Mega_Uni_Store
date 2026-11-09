@php use App\Support\Formato; @endphp

<x-mus.page title="Locales" subtitle="Las sedes en las que trabaja este negocio" icon="branch">
    <x-slot name="actions">
        @can('tiendas.crear')
            @if ($puedeMas)
                <x-mus.btn href="{{ route('tiendas.create') }}" variant="primary" icon="plus">
                    Abrir local
                </x-mus.btn>
            @endif
        @endcan
    </x-slot>

    {{-- El tope del plan, dicho antes de que estorbe y no cuando ya estorbó. --}}
    @if ($panorama && $panorama['limite'])
        <x-mus.panel :reveal="true">
            <p style="margin:0;color:var(--muted-2)">
                Tu plan incluye <b>{{ $panorama['limite'] }}</b>
                {{ $panorama['limite'] === 1 ? 'local' : 'locales' }}
                y tienes <b>{{ $panorama['usado'] }}</b>.
                @unless ($puedeMas)
                    Para abrir otro necesitas subir de plan.
                @endunless
            </p>
        </x-mus.panel>
    @endif

    <x-mus.panel :pad="false" :reveal="true">
        @if ($tiendas->total())
            <x-mus.toolbar :count="$tiendas->total()" label="locales" :action="route('tiendas.index')" :q="$q ?? ''" />

            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Local</th>
                            <th>Código</th>
                            <th>Ciudad</th>
                            <th class="num">Gente</th>
                            <th>Estado</th>
                            <th>Abierto</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tiendas as $t)
                            <tr data-row>
                                <td><span class="mt__id">{{ $t->id }}</span></td>
                                <td>
                                    <b>{{ $t->nombre }}</b>
                                    @if ($t->es_principal)
                                        <x-mus.badge tone="info">Principal</x-mus.badge>
                                    @endif
                                    @if ($t->id === $activaId)
                                        <x-mus.badge tone="ok">Estás aquí</x-mus.badge>
                                    @endif
                                    @if ($t->direccion)
                                        <span class="sub">{{ $t->direccion }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($t->codigo)
                                        <span class="mono">{{ $t->codigo }}</span>
                                    @else
                                        <span style="color:var(--muted-2)">sin prefijo</span>
                                    @endif
                                </td>
                                <td>{{ $t->ciudad ?: '—' }}</td>
                                <td class="num">
                                    <x-mus.badge :tone="$t->usuarios_count ? 'info' : 'off'">{{ $t->usuarios_count }}</x-mus.badge>
                                </td>
                                <td>
                                    <x-mus.badge :tone="$t->activa ? 'ok' : 'off'">
                                        {{ $t->activa ? 'Activo' : 'Cerrado' }}
                                    </x-mus.badge>
                                </td>
                                <td><span style="color:var(--muted-2)">{{ Formato::enPalabras($t->created_at, 'D MMM YYYY', '—') }}</span></td>
                                <td class="act">
                                    <span class="mt__acts">
                                        @can('tiendas.ver')<x-mus.btn href="{{ route('tiendas.show', $t) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Ver">
                                            <x-mus.icon name="eye" :w="15" />
                                        </x-mus.btn>@endcan
                                        @can('tiendas.editar')<x-mus.btn href="{{ route('tiendas.edit', $t) }}"
                                                   variant="ghost" :sm="true" class="mb--icon" title="Editar">
                                            <x-mus.icon name="pencil" :w="15" />
                                        </x-mus.btn>@endcan
                                        @can('tiendas.eliminar')
                                            @unless ($t->es_principal)
                                                <x-mus.del :action="route('tiendas.destroy', $t)" what="el local" />
                                            @endunless
                                        @endcan
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$tiendas" label="locales" />
        @else
            <x-mus.empty icon="branch" title="Un solo local"
                         text="Cuando el negocio abra otra sede, aquí se registra. Cada local lleva sus propias existencias, su caja y su numeración.">
                @can('tiendas.crear')
                    <x-slot name="action">
                        <x-mus.btn href="{{ route('tiendas.create') }}" variant="primary" icon="plus">Abrir local</x-mus.btn>
                    </x-slot>
                @endcan
            </x-mus.empty>
        @endif
    </x-mus.panel>
</x-mus.page>
