@php use App\Support\Formato; @endphp
<x-mus.page title="Consolidado de nómina" subtitle="Costo laboral de todos los locales frente a sus ingresos" icon="chart"
            :crumbs="['Nómina' => route('payrolls.index'), 'Consolidado' => null]">
    <x-slot name="actions">
        <x-mus.btn href="{{ route('payroll_report.excel', ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()]) }}" icon="download">Excel</x-mus.btn>
    </x-slot>

    @include('nomina._nav')

    <x-mus.panel :pad="false" style="margin-bottom:16px">
        <form method="GET" action="{{ route('payroll_report.index') }}" class="nfil">
            <label>Desde <input type="month" name="desde" value="{{ $desde->format('Y-m') }}"></label>
            <label>Hasta <input type="month" name="hasta" value="{{ $hasta->format('Y-m') }}"></label>
            <label>Concepto
                <select name="concepto">
                    @foreach ($conceptos as $codigo => $nombre)<option value="{{ $codigo }}" @selected($concepto === $codigo)>{{ $nombre }}</option>@endforeach
                </select>
            </label>
            <span class="nfil__sp"></span>
            <x-mus.btn type="submit" :sm="true" icon="filter">Ver</x-mus.btn>
        </form>

        <div class="mt-wrap">
            <table class="mt">
                <thead><tr><th>Local</th><th class="num">Nóminas</th><th class="num">Empleados</th><th class="num">Devengado</th><th class="num">Aportes</th><th class="num">Costo laboral</th><th class="num">Ingresos</th><th class="num">Costo / ingresos</th></tr></thead>
                <tbody>
                    @foreach ($filas as $f)
                        <tr>
                            <td><b>{{ $f['tienda']->nombre }}</b></td>
                            <td class="num">{{ $f['nominas'] }}</td>
                            <td class="num">{{ $f['empleados'] }}</td>
                            <td class="num nmon">{{ Formato::moneda($f['devengado']) }}</td>
                            <td class="num nmon">{{ Formato::moneda($f['aportes']) }}</td>
                            <td class="num nmon"><b>{{ Formato::moneda($f['costo']) }}</b></td>
                            <td class="num nmon">{{ Formato::moneda($f['ingresos']) }}</td>
                            <td class="num">
                                @if ($f['ratio'] === null) <span class="sub">sin ventas</span>
                                @else <x-mus.badge :tone="$f['alerta'] ? 'bad' : 'ok'">{{ number_format($f['ratio'], 1, ',', '.') }} %</x-mus.badge> @endif
                            </td>
                        </tr>
                    @endforeach
                    <tr><td><b>Total</b></td><td class="num">{{ $filas->sum('nominas') }}</td><td class="num">{{ $filas->sum('empleados') }}</td>
                        <td class="num nmon"><b>{{ Formato::moneda($filas->sum('devengado')) }}</b></td>
                        <td class="num nmon"><b>{{ Formato::moneda($filas->sum('aportes')) }}</b></td>
                        <td class="num nmon"><b>{{ Formato::moneda($filas->sum('costo')) }}</b></td>
                        <td class="num nmon"><b>{{ Formato::moneda($filas->sum('ingresos')) }}</b></td><td></td></tr>
                </tbody>
            </table>
        </div>
        <p class="nhint">Solo cuentan las nóminas aprobadas o pagadas. Se marca en rojo el local cuyo costo laboral supera el {{ rtrim(rtrim(number_format($alerta, 1, ',', '.'), '0'), ',') }} % de sus ingresos.</p>
    </x-mus.panel>

    <x-mus.panel :pad="false" title="Costo laboral mes a mes" style="margin-bottom:16px">
        <div class="mt-wrap">
            <table class="mt">
                <thead><tr><th>Local</th>@foreach ($comparacion['meses'] as $mes)<th class="num">{{ $mes }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($comparacion['filas'] as $fila)
                        <tr>
                            <td><b>{{ $fila['tienda']->nombre }}</b></td>
                            @foreach ($comparacion['meses'] as $mes)
                                <td class="num nmon">{{ Formato::moneda($fila['meses'][$mes]['costo']) }}
                                    @if ($fila['meses'][$mes]['variacion'] !== null)
                                        <span class="sub" style="display:block">{{ $fila['meses'][$mes]['variacion'] > 0 ? '+' : '' }}{{ number_format($fila['meses'][$mes]['variacion'], 1, ',', '.') }} %</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr><td><b>Total</b></td>@foreach ($comparacion['meses'] as $mes)<td class="num nmon"><b>{{ Formato::moneda($comparacion['totales'][$mes]) }}</b></td>@endforeach</tr>
                </tbody>
            </table>
        </div>
    </x-mus.panel>

    <div class="ngrid">
        <x-mus.panel :pad="false" :title="'Por concepto: ' . ($conceptos[$concepto] ?? $concepto)" :sub="'Total ' . Formato::moneda($porConcepto['total'])">
            @if ($porConcepto['ranking']->count())
                <ul class="nlist">
                    @foreach ($porConcepto['ranking'] as $r)
                        <li><div style="flex:1"><b>{{ $r['tienda'] }}</b>
                                <div class="nbar" style="margin-top:6px"><i style="width:{{ $porConcepto['total'] > 0 ? round($r['total'] / $porConcepto['total'] * 100) : 0 }}%"></i></div></div>
                            <b class="nmon">{{ Formato::moneda($r['total']) }}</b></li>
                    @endforeach
                </ul>
            @else
                <p class="nhint" style="border:0">Ningún local pagó ese concepto en el período.</p>
            @endif
        </x-mus.panel>

        <x-mus.panel :pad="false" title="Por área">
            @if ($areas->count())
                <ul class="nlist">
                    @foreach ($areas as $a)
                        <li><div class="nwho"><b>{{ $a['area'] }}</b><small>{{ $a['empleados'] }} {{ $a['empleados'] === 1 ? 'persona' : 'personas' }}</small></div><b class="nmon">{{ Formato::moneda($a['costo']) }}</b></li>
                    @endforeach
                </ul>
            @else
                <p class="nhint" style="border:0">Sin nóminas definitivas en el período.</p>
            @endif
        </x-mus.panel>
    </div>
</x-mus.page>
