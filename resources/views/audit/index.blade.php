@php
    $tonos = ['creado' => 'ok', 'actualizado' => 'info', 'eliminado' => 'bad', 'restaurado' => 'warn'];
@endphp

<x-mus.page title="Auditoría" subtitle="Quién cambió qué, cuándo y desde dónde" icon="key">

    <div class="skpi" data-reveal data-stagger>
        <div class="skpi__c"><span>Movimientos hoy</span><b data-count="{{ $resumen['hoy'] }}">0</b></div>
        <div class="skpi__c"><span>Últimos 7 días</span><b data-count="{{ $resumen['semana'] }}">0</b></div>
        <div class="skpi__c"><span>Eliminaciones</span>
            <b class="{{ $resumen['borrados'] ? 'bad' : '' }}" data-count="{{ $resumen['borrados'] }}">0</b></div>
        <div class="skpi__c"><span>Total registrado</span><b data-count="{{ $resumen['total'] }}">0</b></div>
    </div>

    <x-mus.panel :pad="false" :reveal="true">
        <form method="GET" action="{{ route('audit.index') }}" class="sfil">
            <div class="sfil__s">
                <x-mus.icon name="search" :w="15" stroke-width="2" />
                <input type="text" name="q" value="{{ $filtros['q'] }}" placeholder="Usuario o registro…">
            </div>

            <select name="usuario" class="sfil__sel">
                <option value="">Todos los usuarios</option>
                @foreach ($usuarios as $u)
                    <option value="{{ $u->id }}" @selected($filtros['usuario'] == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>

            <select name="evento" class="sfil__sel">
                <option value="">Todo tipo de cambio</option>
                @foreach ($eventos as $k => $v)
                    <option value="{{ $k }}" @selected($filtros['evento'] === $k)>{{ $v }}</option>
                @endforeach
            </select>

            <select name="modelo" class="sfil__sel">
                <option value="">Todos los módulos</option>
                @foreach ($modelos as $k => $v)
                    <option value="{{ $k }}" @selected($filtros['modelo'] === $k)>{{ $v }}</option>
                @endforeach
            </select>

            <label class="sfil__f">Desde <input type="date" name="desde" value="{{ $filtros['desde'] }}"></label>
            <label class="sfil__f">Hasta <input type="date" name="hasta" value="{{ $filtros['hasta'] }}"></label>

            <button type="submit" class="mb mb--primary mb--sm">Filtrar</button>
            <a href="{{ route('audit.index') }}" class="mb mb--ghost mb--sm">Limpiar</a>
        </form>

        @if ($registros->total())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Cuándo</th>
                            <th>Quién</th>
                            <th>Qué hizo</th>
                            <th>Sobre qué</th>
                            <th class="num">Campos</th>
                            <th>IP</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($registros as $r)
                            <tr data-row>
                                <td>
                                    {{ $r->created_at?->format('d/m/Y') }}
                                    <span class="sub">{{ $r->created_at?->format('H:i:s') }}</span>
                                </td>
                                <td>
                                    <b>{{ $r->user_name ?? $r->user->name ?? 'Sistema' }}</b>
                                </td>
                                <td>
                                    <x-mus.badge :tone="$tonos[$r->event] ?? 'soft'" :dot="true">
                                        {{ $r->evento_label }}
                                    </x-mus.badge>
                                </td>
                                <td>
                                    <b>{{ $r->auditable_label }}</b>
                                    <span class="sub">{{ $r->modelo }} #{{ $r->auditable_id }}</span>
                                </td>
                                <td class="num">{{ count($r->cambios) ?: '—' }}</td>
                                <td><span class="sub">{{ $r->ip ?: '—' }}</span></td>
                                <td class="act">
                                    <x-mus.btn href="{{ route('audit.show', $r) }}"
                                               variant="ghost" :sm="true" class="mb--icon" title="Ver detalle">
                                        <x-mus.icon name="eye" :w="15" />
                                    </x-mus.btn>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$registros" label="movimientos" />
        @else
            <x-mus.empty icon="key" title="Sin movimientos registrados"
                         text="Desde ahora, cada creación, edición y eliminación queda guardada aquí con su autor." />
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        .skpi{ display:grid; gap:12px; margin-bottom:16px;
               grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); }
        .skpi__c{ padding:14px 16px; background:var(--card); border:1px solid var(--line);
                  border-radius:12px; }
        .skpi__c > span{ display:block; font-size:11.1px; letter-spacing:.05em; text-transform:uppercase;
                         color:var(--muted-2); font-weight:600; }
        .skpi__c b{ display:block; margin:6px 0 2px; font-size:22px; font-weight:700;
                    letter-spacing:-.03em; color:var(--ink); line-height:1.05;
                    font-variant-numeric:tabular-nums; }
        .skpi__c b.bad{ color:var(--bad); }

        .sfil{ display:flex; align-items:center; gap:9px; flex-wrap:wrap;
               padding:13px 16px; border-bottom:1px solid var(--line-2); background:var(--paper); }
        .sfil__s{ display:flex; align-items:center; gap:8px; flex:1 1 190px; min-width:170px;
                  padding:0 12px; height:36px; background:#fff; border:1px solid var(--line);
                  border-radius:9px; color:var(--muted); }
        .sfil__s:focus-within{ border-color:var(--a-400); box-shadow:0 0 0 3px rgba(46,110,168,.12); }
        .sfil__s input{ flex:1; border:0; outline:0; background:transparent; font:inherit;
                        font-size:13px; color:var(--ink); }
        .sfil__f{ display:flex; align-items:center; gap:6px; font-size:12.2px; color:var(--muted); }
        .sfil__f input{ height:36px; padding:0 9px; border:1px solid var(--line); border-radius:9px;
                        background:#fff; font:inherit; font-size:12.6px; color:var(--ink); }
        .sfil__sel{ height:36px; padding:0 28px 0 11px; border:1px solid var(--line); border-radius:9px;
                    background:#fff; font:inherit; font-size:12.6px; color:var(--ink); cursor:pointer; }

        /* ── Celular: un filtro por línea, campos grandes ── */
        @media (max-width:760px){
            .sfil{ gap:8px; }
            .sfil__s{ flex:1 0 100%; min-width:0; height:42px; }
            .sfil__s input{ font-size:16px; }
            .sfil__f{ flex:1 1 100%; flex-wrap:wrap; }
            .sfil__f input{ flex:1 1 120px; min-width:0; height:42px; font-size:16px; }
            .sfil__sel{ flex:1 0 100%; width:100%; min-width:0; height:42px; font-size:16px; }
            .sfil .mb{ flex:1 1 auto; }
        }
    </style>
    @endpush
</x-mus.page>
