@php use App\Support\Formato; @endphp

<x-mus.page title="Anulaciones por aprobar" subtitle="Ventas que un vendedor o cajero pidió anular" icon="receipt"
            :crumbs="['Ventas' => route('sales.index'), 'Anulaciones' => null]">

    <x-mus.panel :pad="false" :reveal="true">
        <form method="GET" action="{{ route('void_requests.index') }}" class="pfil">
            <select name="estado" class="pfil__sel" onchange="this.form.submit()" aria-label="Estado">
                <option value="pendiente" @selected($estado === 'pendiente')>Pendientes</option>
                <option value="aprobada" @selected($estado === 'aprobada')>Aprobadas</option>
                <option value="rechazada" @selected($estado === 'rechazada')>Rechazadas</option>
            </select>
            <span class="pfil__sp"></span>
            <span class="mtool__count">{{ number_format($solicitudes->total(), 0, ',', '.') }} solicitudes</span>
        </form>

        @if ($solicitudes->total())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Venta</th>
                            <th class="num">Total</th>
                            <th>Pidió</th>
                            <th>Motivo</th>
                            <th>{{ $estado === 'pendiente' ? 'Hace' : 'Revisó' }}</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($solicitudes as $s)
                            <tr data-row>
                                <td>
                                    <a href="{{ route('sales.show', $s->sale) }}" class="mt__id">{{ $s->sale->number }}</a>
                                    <span class="sub" style="display:block">{{ $s->sale->sold_at?->format('d/m H:i') }} · {{ $s->sale->user?->nombre_completo }}</span>
                                </td>
                                <td class="num"><b>{{ Formato::moneda($s->sale->total) }}</b></td>
                                <td>{{ $s->requester?->nombre_completo ?? '—' }}</td>
                                <td style="max-width:280px">{{ $s->reason }}</td>
                                <td>
                                    @if ($estado === 'pendiente')
                                        {{ Formato::enPalabras($s->created_at, 'D MMM, HH:mm', '—') }}
                                    @else
                                        {{ $s->reviewer?->nombre_completo ?? '—' }}
                                        <span class="sub" style="display:block">{{ Formato::enPalabras($s->reviewed_at, 'D MMM, HH:mm', '') }}{{ $s->rejection_reason ? ' · ' . $s->rejection_reason : '' }}</span>
                                    @endif
                                </td>
                                <td class="act">
                                    @if ($estado === 'pendiente' && auth()->user()->can('anular', $s->sale))
                                        <div class="pacc">
                                            <form method="POST" action="{{ route('void_requests.aprobar', $s) }}">
                                                @csrf
                                                <button type="submit" class="mb mb--danger mb--sm">Aprobar y anular</button>
                                            </form>
                                            <form method="POST" action="{{ route('void_requests.rechazar', $s) }}">
                                                @csrf
                                                <input type="text" name="motivo" required minlength="5" maxlength="255"
                                                       placeholder="Motivo del rechazo" aria-label="Motivo del rechazo">
                                                <button type="submit" class="mb mb--ghost mb--sm">Rechazar</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-mus.pagination :items="$solicitudes" label="solicitudes" />
        @else
            <x-mus.empty icon="receipt" title="{{ $estado === 'pendiente' ? 'Nada por aprobar' : 'Sin solicitudes ' . $estado . 's' }}"
                         text="Cuando un vendedor o cajero pide anular una venta, la solicitud aparece aquí y te llega un aviso a la campana." />
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        .pfil{ display:flex; align-items:center; gap:9px; flex-wrap:wrap; padding:13px 16px; border-bottom:1px solid var(--line-2); background:var(--paper); }
        .pfil__sel{ height:36px; padding:0 30px 0 11px; border:1px solid var(--line); border-radius:9px; font:inherit; font-size:13px; color:var(--ink); cursor:pointer;
                    background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2366768F' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 11px center; -webkit-appearance:none; appearance:none; }
        .pfil__sp{ flex:1 1 auto; }
        .pacc{ display:flex; gap:6px; align-items:center; justify-content:flex-end; flex-wrap:wrap; }
        .pacc form{ display:flex; gap:6px; align-items:center; margin:0; }
        .pacc input[type=text]{ height:30px; padding:0 9px; border:1px solid var(--line); border-radius:8px; font:inherit; font-size:12.5px; min-width:150px; }
        @media (max-width:760px){ .pfil__sel{ flex:1 0 100%; height:42px; font-size:16px; } .pacc input[type=text]{ min-width:0; flex:1; font-size:16px; height:38px; } }
    </style>
    @endpush
</x-mus.page>
