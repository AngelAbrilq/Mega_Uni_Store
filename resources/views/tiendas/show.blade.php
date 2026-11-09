@php use App\Support\Formato; @endphp

<x-mus.page title="{{ $tienda->nombre }}" subtitle="Local del negocio" icon="branch"
            :crumbs="['Locales' => route('tiendas.index'), 'Detalle' => null]">

    <x-slot name="actions">
        @can('tiendas.editar')
            <x-mus.btn href="{{ route('tiendas.edit', $tienda) }}" icon="pencil">Editar</x-mus.btn>

            @unless ($tienda->es_principal)
                <form method="POST" action="{{ route('tiendas.principal', $tienda) }}" style="display:inline"
                      data-confirm="¿Nombrar «{{ $tienda->nombre }}» como sede principal? La que lo es hoy dejará de serlo.">
                    @csrf
                    <button type="submit" class="mb">Hacer principal</button>
                </form>
            @endunless
        @endcan
    </x-slot>

    <div style="display:grid;gap:16px;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);align-items:start"
         class="mshow-grid">

        <x-mus.panel title="Información" sub="Datos registrados">
            <dl class="mdl">
                <div>
                    <dt>Nombre</dt>
                    <dd>
                        {{ $tienda->nombre }}
                        @if ($tienda->es_principal)<x-mus.badge tone="info">Principal</x-mus.badge>@endif
                        @if ($esLaActiva)<x-mus.badge tone="ok">Estás aquí</x-mus.badge>@endif
                    </dd>
                </div>
                <div>
                    <dt>Prefijo de documentos</dt>
                    <dd>
                        @if ($tienda->codigo)
                            <span class="mono">{{ $tienda->codigo }}-000001</span>
                        @else
                            <span style="color:var(--muted-2)">sin prefijo · V-000001</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>Dirección</dt>
                    <dd>{{ $tienda->direccion ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Ciudad</dt>
                    <dd>{{ $tienda->ciudad ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Teléfono</dt>
                    <dd>{{ $tienda->telefono ?: '—' }}</dd>
                </div>
                <div>
                    <dt>NIT</dt>
                    <dd>
                        {{ $tienda->dato('nit') ?: '—' }}
                        @unless ($tienda->nit)
                            <span class="sub">heredado del negocio</span>
                        @endunless
                    </dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd>
                        <x-mus.badge :tone="$tienda->activa ? 'ok' : 'off'">
                            {{ $tienda->activa ? 'Activo' : 'Cerrado' }}
                        </x-mus.badge>
                    </dd>
                </div>
            </dl>
        </x-mus.panel>

        <div style="display:grid;gap:16px">
            <x-mus.panel title="Cómo va" sub="Lo que ha pasado aquí">
                <dl class="mdl">
                    <div>
                        <dt>Productos a la venta</dt>
                        <dd>{{ number_format($disponibles, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt>Con existencia</dt>
                        <dd>{{ number_format($conStock, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt>Ventas registradas</dt>
                        <dd>{{ number_format($ventas, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt>Gente asignada</dt>
                        <dd>{{ $tienda->usuarios->count() }}</dd>
                    </div>
                </dl>
            </x-mus.panel>

            <x-mus.panel title="Registro" sub="Trazabilidad">
                <dl class="mdl">
                    <div>
                        <dt>Identificador</dt>
                        <dd>#{{ $tienda->id }}</dd>
                    </div>
                    <div>
                        <dt>Abierto</dt>
                        <dd>{{ Formato::enPalabras($tienda->created_at, 'D MMM YYYY, HH:mm', '—') }}</dd>
                    </div>
                </dl>

                <x-slot name="foot">
                    <x-mus.btn href="{{ route('tiendas.index') }}" icon="back" :block="true">
                        Volver al listado
                    </x-mus.btn>
                </x-slot>
            </x-mus.panel>
        </div>
    </div>

    @push('styles')
        <style>
            @media (max-width:900px){ .mshow-grid{ grid-template-columns:minmax(0,1fr) !important; } }
        </style>
    @endpush
</x-mus.page>
