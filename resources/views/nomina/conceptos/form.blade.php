@php
    use App\Models\PayrollConcept;
    $sistema = (bool) $concepto?->is_system;
    $calculos = collect(PayrollConcept::CALCULOS)->except('sistema')->all();
@endphp
<x-mus.page :title="$concepto ? 'Editar concepto' : 'Nuevo concepto'" :subtitle="$concepto?->name ?? 'Un bono, un auxilio o un descuento propio del negocio'" icon="layers"
            :crumbs="['Nómina' => route('payrolls.index'), 'Conceptos' => route('payroll_concepts.index'), ($concepto ? 'Editar' : 'Nuevo') => null]">
    @include('nomina._nav')

    @if ($sistema)
        <div class="naviso naviso--info"><span><x-mus.icon name="info" :w="16" /></span>
            <div><b>Concepto de ley</b><p>Solo se puede cambiar el nombre y la tasa: el código, el tipo y la forma de cálculo los usa el motor de la nómina.</p></div></div>
    @endif

    <x-mus.panel>
        <form method="POST" action="{{ $concepto ? route('payroll_concepts.update', $concepto) : route('payroll_concepts.store') }}" class="mf">
            @csrf
            @if ($concepto) @method('PUT') @endif

            <x-mus.field name="name" label="Nombre" :value="$concepto?->name" :required="true" />
            @unless ($sistema)
                <x-mus.field name="code" label="Código" :value="$concepto?->code" :required="true" hint="Corto y sin espacios. Ej.: BONO_VENTAS" />
                <x-mus.select name="type" label="Tipo" :options="[PayrollConcept::DEVENGADO => 'Devengado (se paga)', PayrollConcept::DEDUCCION => 'Deducción (se descuenta)']" :value="$concepto?->type" :required="true" />
                <x-mus.select name="calculation" label="Cómo se calcula" :options="$calculos" :value="$concepto?->calculation ?? 'fijo'" :required="true" />
            @endunless
            <x-mus.field name="value" label="Valor o porcentaje" type="number" step="0.0001" :value="$concepto ? (float) $concepto->value : null" hint="Pesos si es fijo; porcentaje si es % del salario o del SMLMV. Vacío si es manual." />
            @unless ($sistema)
                <x-mus.toggle name="is_mandatory" label="Aplicar a todos los empleados" hint="Se incluye solo en cada liquidación. No aplica a los manuales." :checked="(bool) ($concepto?->is_mandatory ?? false)" />
                <x-mus.toggle name="is_salary" label="Es salarial" hint="Suma a la base de aportes y prestaciones." :checked="(bool) ($concepto?->is_salary ?? false)" />
            @endunless

            <div class="mf__wide" style="display:flex; gap:8px">
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar</x-mus.btn>
                <x-mus.btn href="{{ route('payroll_concepts.index') }}">Cancelar</x-mus.btn>
            </div>
        </form>
    </x-mus.panel>
</x-mus.page>
