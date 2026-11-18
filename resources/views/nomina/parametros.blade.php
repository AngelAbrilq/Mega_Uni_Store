@php $v = fn (string $clave, $defecto = '') => old(str_replace('nomina.', '', $clave), $ajustes[$clave] ?? $defecto); @endphp
<x-mus.page title="Parámetros de nómina" subtitle="Valores legales que usa el cálculo. Cámbialos cuando salga el decreto de cada año" icon="gear"
            :crumbs="['Nómina' => route('payrolls.index'), 'Parámetros' => null]">
    @include('nomina._nav')

    <div class="naviso naviso--info">
        <span><x-mus.icon name="info" :w="16" /></span>
        <div><b>Estos valores no se actualizan solos</b>
            <p>El salario mínimo y el auxilio de transporte los fija el Gobierno por decreto cada diciembre. Verifícalos en la fuente oficial antes de liquidar enero. Las nóminas ya aprobadas guardan los valores con los que se calcularon.</p></div>
    </div>

    <x-mus.panel title="Valores vigentes">
        <form method="POST" action="{{ route('payrolls.parametros.guardar') }}" class="nform" style="max-width:640px">
            @csrf @method('PUT')
            <div class="nform__2">
                <label>Salario mínimo mensual (SMLMV)
                    <input type="number" name="smlmv" min="1" step="1" value="{{ $v('nomina.smlmv') }}" required>
                    @error('smlmv')<span class="nerr">{{ $message }}</span>@enderror
                </label>
                <label>Auxilio de transporte mensual
                    <input type="number" name="auxilio_transporte" min="0" step="1" value="{{ $v('nomina.auxilio_transporte') }}" required>
                    @error('auxilio_transporte')<span class="nerr">{{ $message }}</span>@enderror
                </label>
            </div>
            <div class="nform__2">
                <label>Horas al mes para el valor de la hora
                    <input type="number" name="horas_mes" min="150" max="240" step="1" value="{{ $v('nomina.horas_mes') === 'auto' ? '' : $v('nomina.horas_mes') }}" placeholder="Automático: {{ $hoy['horas'] }}">
                    <small>Vacío = según la jornada legal vigente hoy ({{ $hoy['horas'] }} h).</small>
                    @error('horas_mes')<span class="nerr">{{ $message }}</span>@enderror
                </label>
                <label>Recargo dominical y festivo (%)
                    <input type="number" name="recargo_dominical" min="0" max="200" step="0.01" value="{{ $v('nomina.recargo_dominical') === 'auto' ? '' : $v('nomina.recargo_dominical') }}" placeholder="Automático: {{ $hoy['dominical'] }}">
                    <small>Vacío = el porcentaje legal vigente hoy ({{ $hoy['dominical'] }} %).</small>
                    @error('recargo_dominical')<span class="nerr">{{ $message }}</span>@enderror
                </label>
            </div>
            <label>Alerta de costo laboral sobre ingresos (%)
                <input type="number" name="ratio_alerta" min="1" max="100" step="0.1" value="{{ $v('nomina.ratio_alerta', 30) }}" required>
                <small>El consolidado marca en rojo el local que supere este porcentaje.</small>
                @error('ratio_alerta')<span class="nerr">{{ $message }}</span>@enderror
            </label>
            <label style="display:flex; grid-template-columns:none; align-items:center; gap:9px">
                <input type="hidden" name="exoneracion_114" value="0">
                <input type="checkbox" name="exoneracion_114" value="1" @checked((string) $v('nomina.exoneracion_114', '0') === '1')>
                <span>Aplicar la exoneración de aportes del art. 114-1 del Estatuto Tributario <small style="display:block">Salud del empleador, SENA e ICBF en cero para quien devengue menos de 10 SMLMV. Confírmalo con tu contador.</small></span>
            </label>
            <div><x-mus.btn type="submit" variant="primary" icon="save">Guardar parámetros</x-mus.btn></div>
        </form>
    </x-mus.panel>
</x-mus.page>
