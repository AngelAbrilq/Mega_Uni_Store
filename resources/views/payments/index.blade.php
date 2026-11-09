@php use App\Support\Formato; @endphp

<x-mus.page title="Pagos por verificar" subtitle="Transferencias y pagos que esperan confirmación" icon="card"
            :crumbs="['Ventas' => route('sales.index'), 'Pagos por verificar' => null]">

    <x-mus.panel :pad="false" :reveal="true">
        <form method="GET" action="{{ route('payments.index') }}" class="pfil">
            <select name="estado" class="pfil__sel" onchange="this.form.submit()" aria-label="Estado">
                <option value="pendiente" @selected($estado === 'pendiente')>Pendientes</option>
                <option value="rechazado" @selected($estado === 'rechazado')>Rechazados</option>
            </select>
            <span class="pfil__sp"></span>
            <span class="mtool__count">{{ number_format($pagos->total(), 0, ',', '.') }} pagos</span>
        </form>

        @if ($pagos->total())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Venta</th>
                            <th>Medio</th>
                            <th>Referencia</th>
                            <th class="num">Valor</th>
                            <th>Cuándo</th>
                            <th>{{ $estado === 'rechazado' ? 'Rechazado por' : 'Vendedor' }}</th>
                            <th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pagos as $pago)
                            <tr data-row>
                                <td><a href="{{ route('sales.show', $pago->sale) }}"><b>{{ $pago->sale->number }}</b></a></td>
                                <td>{{ $pago->method_name }}</td>
                                <td>{{ $pago->reference ?: '—' }}</td>
                                <td class="num"><b>${{ number_format((float) $pago->amount, 0, ',', '.') }}</b></td>
                                <td>{{ Formato::enPalabras($pago->created_at, 'D MMM, HH:mm', '—') }}</td>
                                <td>
                                    @if ($estado === 'rechazado')
                                        {{ $pago->verifier?->nombre_completo ?? '—' }}
                                        <span class="sub" style="display:block">{{ $pago->rejection_reason }}</span>
                                    @else
                                        {{ $pago->sale->user?->nombre_completo ?? '—' }}
                                    @endif
                                </td>
                                <td class="act">
                                    @if ($estado === 'pendiente')
                                        @include('payments._acciones', ['pago' => $pago])
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mus.pagination :items="$pagos" label="pagos" />
        @else
            <x-mus.empty icon="card" title="{{ $estado === 'rechazado' ? 'Sin pagos rechazados' : 'Nada por verificar' }}"
                         text="Los pagos con medios que exigen verificación (transferencias, Nequi…) aparecen aquí hasta que alguien confirme que llegaron." />
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        .pfil{ display:flex; align-items:center; gap:9px; flex-wrap:wrap;
               padding:13px 16px; border-bottom:1px solid var(--line-2); background:var(--paper); }
        .pfil__sel{ height:36px; padding:0 30px 0 11px; border:1px solid var(--line); border-radius:9px;
                    font:inherit; font-size:13px; color:var(--ink); cursor:pointer;
                    background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2366768F' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 11px center; -webkit-appearance:none; appearance:none; }
        .pfil__sp{ flex:1 1 auto; }
        .pacc{ display:flex; gap:6px; align-items:center; justify-content:flex-end; flex-wrap:wrap; }
        .pacc form{ display:flex; gap:6px; align-items:center; margin:0; }
        .pacc input[type=text]{ height:30px; padding:0 9px; border:1px solid var(--line); border-radius:8px;
                                font:inherit; font-size:12.5px; min-width:150px; }
        @media (max-width:760px){
            .pfil__sel{ flex:1 0 100%; height:42px; font-size:16px; }
            .pacc input[type=text]{ min-width:0; flex:1; font-size:16px; height:38px; }
        }
    </style>
    @endpush
</x-mus.page>
