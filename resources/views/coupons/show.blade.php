@php use App\Support\Formato; @endphp

<x-mus.page title="Cupón {{ $cupon->code }}" subtitle="{{ $cupon->description ?: 'Descuento por código' }}" icon="tag"
            :crumbs="['Cupones' => route('coupons.index'), $cupon->code => null]">
    <x-slot name="actions">
        @can('cupones.editar')
            <x-mus.btn href="{{ route('coupons.edit', $cupon) }}" icon="pencil">Editar</x-mus.btn>
        @endcan
    </x-slot>

    <div class="cupk" data-reveal data-stagger>
        <div class="cupk__c"><span>Descuento</span><b>{{ $cupon->etiqueta }}</b>
            <em>{{ $cupon->max_discount ? 'Tope $' . number_format((float) $cupon->max_discount, 0, ',', '.') : 'Sin tope' }}</em></div>
        <div class="cupk__c"><span>Usos</span><b>{{ $cupon->used_count }}{{ $cupon->max_uses ? ' / ' . $cupon->max_uses : '' }}</b>
            <em>{{ $cupon->max_uses ? max(0, $cupon->max_uses - $cupon->used_count) . ' disponibles' : 'Sin límite' }}</em></div>
        <div class="cupk__c"><span>Total descontado</span><b><i class="moneda">$</i>{{ number_format($descontado, 0, ',', '.') }}</b>
            <em>En ventas vigentes y anuladas</em></div>
        <div class="cupk__c"><span>Estado</span><b style="font-size:16px">@include('coupons._estado', ['cupon' => $cupon])</b>
            <em>{{ $cupon->starts_at?->format('d/m/Y') ?? 'Desde ya' }} → {{ $cupon->ends_at?->format('d/m/Y') ?? 'sin vencimiento' }}</em></div>
    </div>

    <x-mus.panel title="Ventas con este cupón" :pad="false" :sub="$usos->count() . ' más recientes'">
        @if ($usos->count())
            <div class="mt-wrap">
                <table class="mt">
                    <thead><tr><th>Venta</th><th>Fecha</th><th>Vendedor</th><th class="num">Descuento</th><th class="num">Total</th></tr></thead>
                    <tbody>
                        @foreach ($usos as $uso)
                            <tr>
                                <td>
                                    @if ($uso->sale)<a href="{{ route('sales.show', $uso->sale) }}"><b>{{ $uso->sale->number }}</b></a>@endif
                                    @if ($uso->sale?->status === 'anulada')<x-mus.badge tone="off">Anulada</x-mus.badge>@endif
                                </td>
                                <td>{{ Formato::enPalabras($uso->sale?->sold_at, 'D MMM YYYY, HH:mm', '—') }}</td>
                                <td>{{ $uso->sale?->user?->nombre_completo ?? '—' }}</td>
                                <td class="num">−${{ number_format((float) $uso->discount_amount, 0, ',', '.') }}</td>
                                <td class="num">${{ number_format((float) ($uso->sale?->total ?? 0), 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-mus.empty icon="tag" title="Aún no se ha usado" text="Cuando un cajero lo aplique en el POS, la venta aparecerá aquí." />
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        .cupk{ display:grid; gap:12px; margin-bottom:16px; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); }
        .cupk__c{ padding:14px 16px; background:var(--card); border:1px solid var(--line); border-radius:12px; }
        .cupk__c > span{ display:block; font-size:11.1px; letter-spacing:.05em; text-transform:uppercase; color:var(--muted-2); font-weight:600; }
        .cupk__c b{ display:block; margin:6px 0 2px; font-size:21px; font-weight:700; letter-spacing:-.03em; color:var(--ink); }
        .cupk__c em{ display:block; font-style:normal; font-size:11.4px; color:var(--muted); }
    </style>
    @endpush
</x-mus.page>
