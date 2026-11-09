@php $max = collect($medios)->max('total') ?: 1; @endphp
@forelse ($medios as $m)
    <div class="mon-m">
        <div class="mon-m__top"><span>{{ $m['nombre'] }}</span><b>{{ \App\Support\Formato::moneda($m['total']) }}</b></div>
        <div class="mon-m__bar"><i style="width:{{ round($m['total'] / $max * 100) }}%"></i></div>
        <small>{{ $m['veces'] }} {{ $m['veces'] === 1 ? 'pago' : 'pagos' }} · {{ $total > 0 ? round($m['total'] / $total * 100) : 0 }} %</small>
    </div>
@empty
    <p class="sub" style="margin:0">Aún no entra plata hoy.</p>
@endforelse
