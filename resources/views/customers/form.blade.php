<div class="mf">
    <div class="mf__grid">
        <x-mus.imagen name="imagen" label="Foto del cliente" :actual="$item?->imagen"
                      hint="Opcional · JPG, PNG o WEBP · máximo 2 MB. Se recorta en cuadrado sola." />

        <x-mus.field name="first_name" label="Nombres" :value="$item?->first_name" :required="true" max="100" />

        <x-mus.field name="last_name" label="Apellidos" :value="$item?->last_name" max="100" />

        <x-mus.field name="email" label="Correo electrónico" type="email" :value="$item?->email"
                     max="150" hint="Opcional, pero no puede repetirse entre clientes." />

        <x-mus.field name="phone" label="Teléfono" :value="$item?->phone" max="20"
                     hint="Solo números y los signos + ( ) -" />

        <x-mus.select name="document_type" label="Tipo de documento" :value="$item?->document_type"
                      empty="Sin especificar"
                      :options="$documentos ?? \App\Http\Controllers\CustomerController::DOCUMENTOS" />

        <x-mus.field name="document_number" label="Número de documento"
                     :value="$item?->document_number" max="30"
                     hint="No puede repetirse entre clientes." />

        <x-mus.field name="address" label="Dirección" :value="$item?->address" :wide="true" max="255" />
    </div>
</div>
