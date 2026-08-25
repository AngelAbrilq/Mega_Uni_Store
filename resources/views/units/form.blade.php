<div class="mf">
    <div class="mf__grid">
        <x-mus.field name="name" label="Nombre" :value="$item?->name" :required="true"
                     max="80" hint="Por ejemplo: Kilogramo, Caja, Metro" />

        <x-mus.field name="symbol" label="Símbolo" :value="$item?->symbol" :required="true"
                     max="10" hint="Abreviatura corta: kg, caj, m" />

        <x-mus.select name="type" label="Tipo de medida" :required="true" :wide="true"
                      :value="$item?->type" empty="Selecciona un tipo"
                      :options="collect($tipos ?? \App\Http\Controllers\UnitController::TIPOS)
                                    ->mapWithKeys(fn ($t) => [$t => $t])->all()" />
    </div>
</div>
