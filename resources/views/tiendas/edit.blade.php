<x-mus.page title="Editar local" subtitle="{{ $tienda->nombre }}" icon="branch"
            :crumbs="['Locales' => route('tiendas.index'), 'Editar' => null]">

    <x-slot name="actions">
        <x-mus.btn href="{{ route('tiendas.show', $tienda) }}" icon="eye">Ver detalle</x-mus.btn>
    </x-slot>

    <form method="POST" action="{{ route('tiendas.update', $tienda) }}" novalidate>
        @csrf
        @method('PUT')

        <x-mus.panel title="Datos del local" sub="Cómo se llama y dónde queda">
            @include('tiendas.form', ['item' => $tienda])

            <div class="mf" style="margin-top:18px">
                <x-mus.toggle name="activa" label="Local abierto"
                              hint="Un local cerrado existe y conserva su historia, pero no se puede vender en él"
                              :checked="$tienda->activa" />
            </div>

            {{-- Cajón monedero del local (RN-28). --}}
            <div class="mf" style="margin-top:18px">
                <p style="margin:0 0 12px;color:var(--muted-2);font-size:.9rem">
                    Cajón monedero. Con el sistema en la nube, el cajón lo abre el
                    <b>agente de caja</b> instalado en el PC del mostrador
                    (carpeta <code>tools/agente-caja</code>).
                </p>
                <div class="mf__grid">
                    <x-mus.select name="drawer_mode" label="¿Cómo se abre el cajón?"
                                  :options="\App\Models\Tienda::MODOS_CAJON" :value="$tienda->drawer_mode" />
                    <x-mus.field name="agent_port" label="Puerto del agente" type="number"
                                 :value="$tienda->agent_port" hint="El mismo del config.json del agente. Normalmente 9123" />
                </div>
            </div>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('tiendas.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cambios</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>

    @if ($tienda->drawer_mode === \App\Models\Tienda::CAJON_AGENTE)
        <x-mus.panel title="Token del agente de caja" sub="Va en el config.json del PC de este local" style="margin-top:18px">
            <div class="mf">
                <p style="margin:0 0 10px">
                    <code id="tokenAgente" style="user-select:all;font-size:1rem">{{ $tienda->asegurarTokenDeAgente() }}</code>
                </p>
                <p style="margin:0;color:var(--muted-2);font-size:.9rem">
                    Sin este token el agente no obedece: así ninguna otra página abierta en ese PC puede abrir el cajón.
                    Si el PC cambió o el token se filtró, renuévalo y cópialo de nuevo en el agente.
                </p>
            </div>
            <x-slot name="foot">
                <form method="POST" action="{{ route('tiendas.agente.token', $tienda) }}">
                    @csrf
                    <x-mus.btn type="submit" variant="danger" icon="alert">Renovar token</x-mus.btn>
                </form>
            </x-slot>
        </x-mus.panel>
    @endif
</x-mus.page>
