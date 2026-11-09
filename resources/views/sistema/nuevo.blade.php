@php use App\Support\Formato; @endphp

{{--
    Alta de un cliente, a mano.

    Es la pantalla que usa Angel cuando el negocio llega por teléfono o por
    una reunión, y no por /probar. Pide lo mismo que la demostración —el
    rubro es lo primero, porque decide con qué arranca— y agrega las dos
    cosas que un cliente de verdad sí tiene y una prueba no: el plan que
    contrató y la decisión de si quiere el catálogo de ejemplo o no.
--}}
<x-mus.page title="Nuevo negocio" subtitle="Dar de alta a un cliente sin pasar por la demostración" icon="globe"
            :crumbs="['Todos los negocios' => route('sistema.index'), 'Nuevo' => null]">

    <form method="POST" action="{{ route('sistema.crear') }}" novalidate>
        @csrf

        <x-mus.panel title="Qué clase de negocio es" sub="Decide los cargos, las unidades y las categorías con las que arranca">
            <div class="msis__rubros">
                @foreach ($rubros as $slug => [$nombre, $descripcion])
                    <label class="msis__rubro">
                        <input type="radio" name="rubro" value="{{ $slug }}" @checked(old('rubro') === $slug)>
                        <span>
                            <b>{{ $nombre }}</b>
                            <em>{{ $descripcion }}</em>
                        </span>
                    </label>
                @endforeach
            </div>

            @error('rubro')
                <p class="mfld__err" style="margin-top:10px">
                    <x-mus.icon name="alert" :w="12" stroke-width="2.2" /><span>{{ $message }}</span>
                </p>
            @enderror
        </x-mus.panel>

        <x-mus.panel title="El negocio" sub="Como va a salir impreso en sus recibos">
            <div class="mf">
                <div class="mf__grid">
                    <x-mus.field name="negocio" label="Nombre del negocio" :required="true" max="120"
                                 hint="Peluquería Doña Marta" />
                    <x-mus.field name="nit" label="NIT" max="30" hint="Opcional, se puede poner después" />
                </div>
            </div>
        </x-mus.panel>

        <x-mus.panel title="El dueño" sub="La cuenta con la que va a entrar">
            <div class="mf">
                <div class="mf__grid">
                    <x-mus.field name="nombre" label="Nombre de quien manda" :required="true" max="120" />
                    <x-mus.field name="email" label="Correo" type="email" :required="true" max="150"
                                 hint="Con este entra al sistema" />
                    <x-mus.field name="telefono" label="Teléfono" max="30" />
                    <x-mus.field name="password" label="Contraseña" type="password" :required="true"
                                 hint="Mínimo 8 caracteres. Dísela y que la cambie desde su perfil" />
                </div>
            </div>
        </x-mus.panel>

        <x-mus.panel title="Qué contrató" sub="El plan decide sus topes de locales, usuarios y productos">
            <div class="mf">
                <div class="mf__grid">
                    <x-mus.select name="plan_id" label="Plan" :wide="true"
                                  empty="Sin plan todavía — sin topes"
                                  :options="$planes->mapWithKeys(fn ($p) => [
                                      $p->id => $p->nombre . ' · ' . Formato::moneda($p->precio_mensual) . '/mes',
                                  ])->all()" />
                </div>

                <x-mus.toggle name="con_muestra" label="Arrancar con productos de ejemplo"
                              hint="Los mismos de la demostración de ese rubro. Útil para mostrárselo; estorba si el cliente va a cargar su propio catálogo — le tocaría borrarlos uno por uno. Las unidades, los impuestos y los medios de pago entran de todas formas."
                              :checked="false" />
            </div>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('sistema.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Crear el negocio</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>

    @push('styles')
        <style>
            .msis__rubros{ display:grid; gap:10px; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); }
            .msis__rubro input{ position:absolute; opacity:0; pointer-events:none; }
            .msis__rubro span{
                display:block; padding:12px 14px; border:1px solid var(--linea);
                border-radius:12px; cursor:pointer; transition:border-color .15s, background .15s;
            }
            .msis__rubro b{ display:block; font-size:.95rem; }
            .msis__rubro em{ display:block; margin-top:2px; font-style:normal; font-size:.82rem; color:var(--muted-2); }
            .msis__rubro span:hover{ border-color:var(--acento); }
            .msis__rubro input:checked + span{ border-color:var(--acento); background:var(--acento-suave, rgba(46,110,168,.08)); }
            .msis__rubro input:focus-visible + span{ outline:2px solid var(--acento); outline-offset:2px; }
        </style>
    @endpush
</x-mus.page>
