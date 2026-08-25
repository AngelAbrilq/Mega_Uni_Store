<x-mus.page title="Nuevo traslado" subtitle="Mover mercancía de un local a otro" icon="back"
            :crumbs="['Traslados' => route('traslados.index'), 'Nuevo' => null]">

    @if ($unLocal)
        <x-mus.panel :reveal="true">
            <x-mus.empty icon="back" title="Este negocio tiene un solo local"
                         text="Un traslado necesita un origen y un destino distintos. Cuando abras la segunda sede, esta pantalla se enciende sola." />
        </x-mus.panel>
    @else
        <form method="POST" action="{{ route('traslados.store') }}" novalidate>
            @csrf

            <x-mus.panel title="Qué se mueve" sub="El origen tiene que tener la mercancía">
                <div class="mtf">
                    <label class="mtf__ancho">
                        <span>Producto</span>
                        {{-- Se escribe el id a mano y no hay buscador todavía: el
                             buscador de productos del punto de venta se reusará
                             aquí en la siguiente vuelta. Por ahora, el listado
                             de inventario muestra el id de cada producto. --}}
                        <input type="number" name="product_id" value="{{ old('product_id') }}"
                               required placeholder="Id del producto (lo ves en Inventario)">
                        @error('product_id')<i>{{ $message }}</i>@enderror
                    </label>

                    <label>
                        <span>Sale de</span>
                        <select name="desde" required>
                            @foreach ($locales as $l)
                                <option value="{{ $l->id }}" @selected(old('desde', $origen) == $l->id)>
                                    {{ $l->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @error('desde')<i>{{ $message }}</i>@enderror
                    </label>

                    <label>
                        <span>Llega a</span>
                        <select name="hacia" required>
                            @foreach ($locales as $l)
                                <option value="{{ $l->id }}" @selected(old('hacia') == $l->id)>
                                    {{ $l->nombre }}
                                </option>
                            @endforeach
                        </select>
                        @error('hacia')<i>{{ $message }}</i>@enderror
                    </label>

                    <label>
                        <span>Cantidad</span>
                        <input type="number" name="cantidad" value="{{ old('cantidad') }}"
                               step="0.001" min="0.001" required placeholder="0">
                        @error('cantidad')<i>{{ $message }}</i>@enderror
                    </label>

                    <label class="mtf__ancho">
                        <span>Notas <small>opcional</small></span>
                        <input type="text" name="notas" value="{{ old('notas') }}" maxlength="255"
                               placeholder="Va con Jorge en la camioneta, llega el jueves">
                        @error('notas')<i>{{ $message }}</i>@enderror
                    </label>
                </div>

                <p class="mtf__nota">
                    El traslado descuenta del origen y suma al destino en el mismo instante, y deja
                    las dos líneas amarradas en el kardex con un número. No se puede mover más
                    de lo que hay: si el origen no alcanza, el sistema dice cuánto hay de verdad.
                </p>

                <x-slot name="foot">
                    <x-mus.btn href="{{ route('traslados.index') }}" icon="back">Cancelar</x-mus.btn>
                    <x-mus.btn type="submit" variant="primary" icon="save">Registrar traslado</x-mus.btn>
                </x-slot>
            </x-mus.panel>
        </form>
    @endif

    @push('styles')
    <style>
        /* minmax(0,…): sin él, un nombre de local largo ensancha su columna
           y los campos dejan de alinearse entre sí. */
        .mtf{ display:grid; grid-template-columns:repeat(auto-fit, minmax(min(200px,100%), 1fr)); gap:14px; }
        .mtf label{ display:block; min-width:0; }
        .mtf > label > span{ display:block; margin-bottom:5px; font-size:12px; color:var(--ink-2); }
        .mtf small{ color:var(--muted); font-size:10.6px; }
        .mtf input, .mtf select{ width:100%; padding:10px 12px; border-radius:9px;
                    border:1px solid var(--line); background:var(--card); color:var(--ink);
                    font-size:13.2px; font-family:inherit; }
        .mtf input:focus, .mtf select:focus{ outline:none; border-color:var(--a-500); }
        .mtf i{ display:block; margin-top:4px; font-style:normal; font-size:11.4px; color:var(--bad); }
        .mtf__ancho{ grid-column:1 / -1; }
        .mtf__nota{ margin:16px 0 0; max-width:70ch; font-size:12.2px; line-height:1.6; color:var(--muted); }
    </style>
    @endpush
</x-mus.page>
