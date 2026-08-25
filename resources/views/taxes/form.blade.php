<div class="mf">
    <div class="mf__grid">
        <x-mus.field name="name" label="Nombre" :value="$item?->name" :required="true"
                     max="80" hint="Por ejemplo: IVA general" />

        <x-mus.field name="rate" label="Tarifa" type="number" step="0.01" min="0"
                     :value="$item?->rate" :required="true"
                     hint="Si es porcentaje, escribe 19 para 19%." />

        <x-mus.select name="type" label="Tipo de cálculo" :required="true"
                      :value="$item?->type ?? 'percentage'"
                      :options="$tipos ?? \App\Http\Controllers\TaxController::TIPOS" />

        <x-mus.toggle name="is_active" label="Impuesto activo"
                      hint="Solo los activos se ofrecen al facturar" :wide="false"
                      :checked="$item?->is_active ?? true" />

        <x-mus.textarea name="description" label="Descripción" :value="$item?->description"
                        hint="Opcional. Nota interna sobre cuándo aplica." />
    </div>
</div>
