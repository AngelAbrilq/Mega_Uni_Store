@php use App\Support\Formato; use App\Models\SocialBenefit; @endphp
<x-mus.page title="Prestaciones sociales" subtitle="Prima, cesantías, intereses y liquidación final" icon="wallet"
            :crumbs="['Nómina' => route('payrolls.index'), 'Prestaciones' => null]">
    @include('nomina._nav')

    @foreach (['tipo', 'period_start', 'period_end', 'employee_id', 'fecha', 'ids', 'prestacion'] as $campo)
        @error($campo)<div class="naviso naviso--bad" role="alert"><span><x-mus.icon name="alert" :w="16" /></span><div><b>{{ $message }}</b></div></div>@enderror
    @endforeach

    <div class="nk">
        <div class="nk__c nk__c--main"><span>Por pagar</span><b>{{ Formato::moneda($porPagar) }}</b><small>prestaciones calculadas sin pagar</small></div>
        @foreach (SocialBenefit::TIPOS as $clave => $nombre)
            <div class="nk__c"><span>{{ $nombre }}</span><b>{{ Formato::moneda($porTipo[$clave] ?? 0) }}</b></div>
        @endforeach
    </div>

    <div class="ngrid">
        <x-mus.panel :pad="false" title="Prestaciones calculadas">
            <form method="GET" action="{{ route('benefits.index') }}" class="nfil">
                <label>Tipo
                    <select name="tipo"><option value="">Todos</option>
                        @foreach (SocialBenefit::TIPOS as $clave => $nombre)<option value="{{ $clave }}" @selected($tipo === $clave)>{{ $nombre }}</option>@endforeach
                    </select>
                </label>
                <label>Estado
                    <select name="estado"><option value="">Todos</option>
                        <option value="{{ SocialBenefit::CALCULADA }}" @selected($estado === SocialBenefit::CALCULADA)>Por pagar</option>
                        <option value="{{ SocialBenefit::PAGADA }}" @selected($estado === SocialBenefit::PAGADA)>Pagadas</option>
                    </select>
                </label>
                <span class="nfil__sp"></span>
                <x-mus.btn type="submit" :sm="true" icon="filter">Filtrar</x-mus.btn>
            </form>

            @if ($prestaciones->total())
                @can('nomina.aprobar')<form method="POST" action="{{ route('benefits.pagar') }}" id="formPagar" data-confirm="¿Registrar el pago de las prestaciones seleccionadas?">@csrf</form>@endcan
                <div class="mt-wrap">
                    <table class="mt">
                        <thead><tr><th></th><th>Empleado</th><th>Prestación</th><th>Período</th><th class="num">Días</th><th class="num">Base</th><th class="num">Valor</th><th>Estado</th><th class="act"></th></tr></thead>
                        <tbody>
                            @foreach ($prestaciones as $p)
                                <tr>
                                    <td>@if ($p->status === SocialBenefit::CALCULADA)@can('nomina.aprobar')<input type="checkbox" name="ids[]" value="{{ $p->id }}" form="formPagar" aria-label="Seleccionar para pagar">@endcan @endif</td>
                                    <td class="nwho"><b>{{ $p->employee?->nombre_completo ?? '#' . $p->employee_id }}</b>@if ($p->employee?->trashed())<small>Retirado</small>@endif</td>
                                    <td>{{ $p->tipoTexto() }}</td>
                                    <td class="sub">{{ Formato::fecha($p->period_start) }} → {{ Formato::fecha($p->period_end) }}</td>
                                    <td class="num">{{ $p->days }}</td>
                                    <td class="num nmon">{{ Formato::moneda($p->base) }}</td>
                                    <td class="num nmon"><b>{{ Formato::moneda($p->amount) }}</b></td>
                                    <td>@include('nomina._estado', ['estado' => $p->status, 'texto' => $p->status === SocialBenefit::PAGADA ? 'Pagada' : 'Por pagar'])</td>
                                    <td class="act">@if ($p->status === SocialBenefit::CALCULADA)@can('nomina.gestionar')<x-mus.del :action="route('benefits.destroy', $p)" what="esta prestación" />@endcan @endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @can('nomina.aprobar')
                    <p class="nhint"><button type="submit" form="formPagar" class="mb mb--primary mb--sm">Registrar pago de las seleccionadas</button></p>
                @endcan
                <x-mus.pagination :items="$prestaciones" label="prestaciones" />
            @else
                <x-mus.empty icon="wallet" title="Sin prestaciones calculadas" text="Calcula la prima del semestre o las cesantías del año desde el panel de la derecha." />
            @endif
        </x-mus.panel>

        @can('nomina.gestionar')
            <div style="display:grid; gap:16px">
                <x-mus.panel title="Calcular para todo el local">
                    <form method="POST" action="{{ route('benefits.store') }}" class="nform">
                        @csrf
                        <label>Prestación
                            <select name="tipo" required>
                                <option value="prima" @selected(old('tipo') === 'prima')>Prima de servicios (semestre)</option>
                                <option value="cesantias" @selected(old('tipo') === 'cesantias')>Cesantías e intereses (año)</option>
                            </select>
                        </label>
                        <div class="nform__2">
                            <label>Desde <input type="date" name="period_start" value="{{ old('period_start', today()->month <= 6 ? today()->startOfYear()->toDateString() : today()->startOfYear()->addMonths(6)->toDateString()) }}" required></label>
                            <label>Hasta <input type="date" name="period_end" value="{{ old('period_end', today()->month <= 6 ? today()->startOfYear()->addMonths(5)->endOfMonth()->toDateString() : today()->endOfYear()->toDateString()) }}" required></label>
                        </div>
                        <x-mus.btn type="submit" variant="primary" icon="check">Calcular</x-mus.btn>
                    </form>
                    <p class="nhint">No duplica: lo que ya estaba calculado para ese período se respeta.</p>
                </x-mus.panel>

                <x-mus.panel title="Liquidación final" sub="Al terminar el contrato de un empleado">
                    <form method="POST" action="{{ route('benefits.final') }}" class="nform">
                        @csrf
                        <label>Empleado
                            <select name="employee_id" required>
                                <option value="">Selecciona…</option>
                                @foreach ($empleados as $e)<option value="{{ $e->id }}" @selected(old('employee_id') == $e->id)>{{ $e->first_name }} {{ $e->last_name }}{{ $e->trashed() ? ' (retirado)' : '' }}</option>@endforeach
                            </select>
                        </label>
                        <label>Fecha de retiro <input type="date" name="fecha" value="{{ old('fecha', today()->toDateString()) }}" required></label>
                        <x-mus.btn type="submit" icon="check">Calcular liquidación</x-mus.btn>
                    </form>
                </x-mus.panel>
            </div>
        @endcan
    </div>
</x-mus.page>
