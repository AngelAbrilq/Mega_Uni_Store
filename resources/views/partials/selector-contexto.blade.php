@php
    /**
     * Selector de negocio y local, en la barra superior.
     *
     * ── Cuándo se pinta ──
     *
     * Solo cuando hay algo que escoger: más de un negocio, o más de un
     * local. En una instalación de un solo local —que van a ser la mayoría
     * al principio— no aparece nada, y la barra queda igual de limpia que
     * antes de que existiera el multitienda.
     *
     * ── Por qué cada opción es un formulario ──
     *
     * Cambiar de local cambia dónde caen las ventas que se registren
     * después. Eso no puede viajar por GET: un enlace que cambia algo con
     * solo abrirlo se dispara con cualquier precarga del navegador, y el
     * cajero terminaría vendiendo en otro local sin haber tocado nada.
     */

    $ctxUsuario = auth()->user();
    $ctxNegocios = collect();
    $ctxOpciones = 0;

    if ($ctxUsuario) {
        $ctxNegocios = $ctxUsuario->empresas()->orderBy('empresas.nombre')->get()
            ->map(function ($empresa) use ($ctxUsuario) {
                $locales = $ctxUsuario->tiendasEn($empresa->id);

                return [
                    'empresa'  => $empresa,
                    'locales'  => $locales,
                    'activa'   => $empresa->estaActiva(),
                ];
            });

        // Cuántas puertas hay en total. Con una sola, no hay nada que elegir.
        $ctxOpciones = $ctxNegocios->sum(fn ($n) => max(1, $n['locales']->count()));
    }

    $ctxEmpresa = \App\Support\Contexto::empresa();
    $ctxTienda  = \App\Support\Contexto::tienda();
@endphp

@if ($ctxUsuario && $ctxOpciones > 1)
    <div class="mctx" id="musCtx">
        <button class="mctx__btn" type="button" id="musCtxBtn"
                aria-haspopup="true" aria-expanded="false"
                aria-label="{{ __('mus.contexto.titulo') }}">
            <span class="mctx__ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3.5 9.5 5 4.5h14l1.5 5"/>
                    <path d="M3.5 9.5a2.6 2.6 0 0 0 5.2 0 2.6 2.6 0 0 0 5.2 0 2.6 2.6 0 0 0 5.2 0"/>
                    <path d="M5 11.5V19a.8.8 0 0 0 .8.8h12.4a.8.8 0 0 0 .8-.8v-7.5"/>
                    <path d="M10 19.8v-4.6h4v4.6"/>
                </svg>
            </span>
            <span class="mctx__txt">
                <b>{{ $ctxTienda?->nombre ?? __('mus.contexto.sin_local') }}</b>
                <i>{{ $ctxEmpresa?->nombre }}</i>
            </span>
            <svg class="mctx__caret" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </button>

        <div class="mctx__box" id="musCtxBox">
            <header>
                <b>{{ __('mus.contexto.titulo') }}</b>
                <span>{{ __('mus.contexto.aqui_vendes') }}</span>
            </header>

            @foreach ($ctxNegocios as $ctxN)
                @php $ctxE = $ctxN['empresa']; @endphp

                <div class="mctx__neg">
                    <span>{{ $ctxE->nombre }}</span>
                    @unless ($ctxN['activa'])
                        <em>{{ $ctxE->haVencido()
                                ? __('mus.contexto.est_vencida')
                                : __('mus.contexto.est_suspendida') }}</em>
                    @endunless
                </div>

                @forelse ($ctxN['locales'] as $ctxL)
                    @php
                        $ctxAqui = $ctxTienda?->id === $ctxL->id && $ctxEmpresa?->id === $ctxE->id;
                    @endphp
                    <form method="POST" action="{{ route('contexto.cambiar') }}" data-sin-ctx>
                        @csrf
                        <input type="hidden" name="empresa_id" value="{{ $ctxE->id }}">
                        <input type="hidden" name="tienda_id" value="{{ $ctxL->id }}">
                        <button type="submit"
                                class="mctx__op {{ $ctxAqui ? 'is-on' : '' }}"
                                @disabled(! $ctxN['activa'])
                                @if ($ctxAqui) aria-current="true" @endif>
                            <span class="mctx__punto" aria-hidden="true"></span>
                            <span class="mctx__nom">
                                {{ $ctxL->nombre }}
                                @if ($ctxL->codigo)
                                    <kbd>{{ $ctxL->codigo }}</kbd>
                                @endif
                            </span>
                            @if ($ctxL->ciudad ?: $ctxE->ciudad)
                                <em>{{ $ctxL->ciudad ?: $ctxE->ciudad }}</em>
                            @endif
                        </button>
                    </form>
                @empty
                    <p class="mctx__vacio">{{ __('mus.contexto.sin_local') }}</p>
                @endforelse
            @endforeach
        </div>
    </div>
@endif
