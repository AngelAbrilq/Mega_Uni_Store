@php use App\Support\Formato; use App\Models\Plan; @endphp

<x-mus.page title="Planes" subtitle="El catálogo que se vende: precio, topes y funciones" icon="card"
            :crumbs="['Todos los negocios' => route('sistema.index'), 'Planes' => null]">
    <x-slot name="actions">
        <x-mus.btn href="{{ route('sistema.planes.create') }}" variant="primary" icon="plus">Nuevo plan</x-mus.btn>
    </x-slot>

    @error('plan')
        <div class="pln-err"><x-mus.icon name="alert" :w="15" /> {{ $message }}</div>
    @enderror

    <div class="pln-grid">
        @forelse ($planes as $p)
            <article class="pln-card {{ $p->is_active ? '' : 'is-off' }}">
                <header>
                    <div>
                        <h3>{{ $p->nombre }}</h3>
                        <span class="pln-slug">{{ $p->slug }}</span>
                    </div>
                    <div class="pln-tags">
                        @if ($p->es_demo)<x-mus.badge tone="info">Demostración</x-mus.badge>@endif
                        @if (! $p->is_active)
                            <x-mus.badge tone="off" :dot="true">Inactivo</x-mus.badge>
                        @elseif (! $p->visible)
                            <x-mus.badge tone="warn" :dot="true">Oculto</x-mus.badge>
                        @else
                            <x-mus.badge tone="ok" :dot="true">A la venta</x-mus.badge>
                        @endif
                    </div>
                </header>

                <div class="pln-precio">
                    <b>{{ Formato::moneda($p->precio_mensual) }}</b><span>/mes</span>
                    <small>
                        {{ Formato::moneda($p->precio_anual) }} al año
                        @if ($p->porcentajeAhorro() > 0) · ahorra {{ $p->porcentajeAhorro() }} % @endif
                    </small>
                </div>

                @if ($p->descripcion)<p class="pln-desc">{{ $p->descripcion }}</p>@endif

                <dl class="pln-lims">
                    <div><dt>Locales</dt><dd>{{ $p->limiteTexto('tiendas') }}</dd></div>
                    <div><dt>Usuarios</dt><dd>{{ $p->limiteTexto('usuarios') }}</dd></div>
                    <div><dt>Productos</dt><dd>{{ $p->limiteTexto('productos') }}</dd></div>
                    <div><dt>Ventas/mes</dt><dd>{{ $p->limiteTexto('ventas_mes') }}</dd></div>
                </dl>

                <div class="pln-funs">
                    @foreach ($p->funciones ?? [] as $f)
                        <span>{{ Plan::FUNCIONES[$f] ?? $f }}</span>
                    @endforeach
                </div>

                <footer>
                    <span class="pln-uso">
                        <x-mus.icon name="users" :w="14" />
                        {{ $p->empresas_count }} {{ $p->empresas_count === 1 ? 'negocio' : 'negocios' }}
                    </span>
                    <span class="pln-acts">
                        <x-mus.btn href="{{ route('sistema.planes.edit', $p) }}" :sm="true" icon="pencil">Editar</x-mus.btn>
                        @unless ($p->es_demo)
                            <form method="POST" action="{{ route('sistema.planes.estado', $p) }}">
                                @csrf
                                <input type="hidden" name="activo" value="{{ $p->is_active ? 0 : 1 }}">
                                <x-mus.btn type="submit" :sm="true" :icon="$p->is_active ? 'close' : 'check'">
                                    {{ $p->is_active ? 'Desactivar' : 'Activar' }}
                                </x-mus.btn>
                            </form>
                            @if ($p->empresas_count === 0)
                                <x-mus.del :action="route('sistema.planes.destroy', $p)" what="el plan {{ $p->nombre }}" />
                            @endif
                        @endunless
                    </span>
                </footer>
            </article>
        @empty
            <x-mus.empty icon="card" title="Todavía no hay planes" text="Crea el primero para poder suscribir negocios." />
        @endforelse
    </div>

    @push('styles')
    <style>
        .pln-err{ display:flex; gap:8px; align-items:center; margin-bottom:14px; padding:10px 14px; border-radius:10px;
                  background:rgba(220,38,38,.08); color:#b91c1c; font-size:13px; font-weight:600; }
        .pln-grid{ display:grid; gap:16px; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); align-items:stretch; }
        .pln-card{ display:flex; flex-direction:column; gap:14px; padding:18px; border-radius:16px; background:var(--card, #fff);
                   border:1px solid var(--line, #e7e9ee); box-shadow:0 1px 2px rgba(16,24,40,.04); }
        .pln-card.is-off{ opacity:.62; background:repeating-linear-gradient(135deg, transparent 0 10px, rgba(0,0,0,.015) 10px 20px), var(--card, #fff); }
        .pln-card header{ display:flex; justify-content:space-between; gap:10px; align-items:flex-start; }
        .pln-card h3{ margin:0; font-size:17px; letter-spacing:-.01em; }
        .pln-slug{ font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:11px; opacity:.55; }
        .pln-tags{ display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end; }
        .pln-precio b{ font-size:26px; letter-spacing:-.03em; font-variant-numeric:tabular-nums; }
        .pln-precio span{ font-size:13px; opacity:.6; margin-left:2px; }
        .pln-precio small{ display:block; font-size:12px; opacity:.65; margin-top:2px; }
        .pln-desc{ margin:0; font-size:13px; line-height:1.45; opacity:.8; }
        .pln-lims{ display:grid; grid-template-columns:1fr 1fr; gap:8px; margin:0; }
        .pln-lims div{ padding:8px 10px; border-radius:10px; background:var(--n-50, #f6f7f9); }
        .pln-lims dt{ font-size:11px; opacity:.6; }
        .pln-lims dd{ margin:0; font-weight:700; font-size:13.5px; font-variant-numeric:tabular-nums; }
        .pln-funs{ display:flex; flex-wrap:wrap; gap:5px; }
        .pln-funs span{ font-size:11.5px; padding:3px 8px; border-radius:999px; background:rgba(46,110,168,.08); color:var(--a-700, #1f4f7a); font-weight:600; }
        .pln-card footer{ margin-top:auto; display:flex; justify-content:space-between; align-items:center; gap:8px;
                          padding-top:12px; border-top:1px solid var(--line, #e7e9ee); flex-wrap:wrap; }
        .pln-uso{ display:inline-flex; gap:6px; align-items:center; font-size:12.5px; opacity:.7; }
        .pln-acts{ display:inline-flex; gap:6px; align-items:center; }
    </style>
    @endpush
</x-mus.page>
