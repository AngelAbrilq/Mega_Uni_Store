<x-mus.page :title="__('mus.pos.titulo')" :subtitle="__('mus.pos.subtitulo')" icon="money">
    <x-slot name="actions">
        @if ($turno)
            <x-mus.btn href="{{ route('cash.show', $turno) }}" icon="clock">
                {{ __('mus.pos.turno_abierto', ['base' => '$' . number_format((float) $turno->opening_amount, 0, ',', '.')]) }}
            </x-mus.btn>
        @elseif (Route::has('cash.create'))
            <x-mus.btn href="{{ route('cash.create') }}" variant="primary" icon="money">{{ __('mus.caja.abrir') }}</x-mus.btn>
        @endif
        <x-mus.btn href="{{ route('sales.index') }}" icon="back">{{ __('mus.pos.ventas_dia') }}</x-mus.btn>
    </x-slot>

    @unless ($turno)
        <div class="pos-aviso" data-reveal>
            <span><x-mus.icon name="alert" :w="16" stroke-width="2.2" /></span>
            <div>
                <b>{{ __('mus.pos.sin_turno') }}</b>
                <p>{{ __('mus.pos.sin_turno_detalle') }}</p>
            </div>
        </div>
    @endunless

    <form method="POST" action="{{ route('pos.store') }}" id="posForm" novalidate>
        @csrf

        <div class="pos">
            {{-- ══════════ Izquierda: catálogo ══════════ --}}
            <section class="pos-cat" data-reveal="left">
                <div class="pos-cat__top">
                    <div class="pos-buscar">
                        <x-mus.icon name="search" :w="16" stroke-width="2" />
                        <input type="text" id="posBuscar" autocomplete="off" autofocus
                               placeholder="{{ __('mus.pos.buscar_ph') }}">
                        <kbd>F2</kbd>
                    </div>
                    <span class="pos-cat__n" id="posCuenta"></span>
                </div>

                <div class="pos-grid" id="posGrid"></div>
                <div class="pos-vacio" id="posVacio" hidden>
                    <x-mus.icon name="search" :w="26" stroke-width="1.5" />
                    <p>{{ __('mus.pos.sin_coincidencias') }}</p>
                </div>
            </section>

            {{-- ══════════ Derecha: carrito ══════════ --}}
            <section class="pos-tic" data-reveal="right">
                <header class="pos-tic__head">
                    <div>
                        <h3>{{ __('mus.pos.venta_en_curso') }}</h3>
                        <p id="posResumen">{{ __('mus.pos.sin_productos') }}</p>
                    </div>
                    <button type="button" class="mb mb--ghost mb--sm" id="posVaciar">{{ __('mus.acciones.limpiar') }}</button>
                </header>

                <div class="pos-tic__body" id="posLineas"></div>

                <div class="pos-tic__empty" id="posTicVacio">
                    <x-mus.icon name="box" :w="26" stroke-width="1.5" />
                    <p>{{ __('mus.pos.toca_para_agregar') }}</p>
                </div>

                <div class="pos-tot">
                    <div><span>{{ __('mus.campos.subtotal') }}</span><b id="totSub">$0</b></div>
                    <div><span>{{ __('mus.campos.descuentos') }}</span><b id="totDesc">$0</b></div>
                    <div><span>{{ __('mus.campos.impuestos') }}</span><b id="totImp">$0</b></div>
                    <div class="pos-tot__big"><span>{{ __('mus.campos.total') }}</span><b id="totTotal">$0</b></div>
                </div>

                <div class="pos-tic__foot">
                    <select name="customer_id" class="pos-sel" id="posCliente">
                        <option value="">{{ __('mus.vacio.consumidor_final') }}</option>
                        @foreach ($clientes as $c)
                            <option value="{{ $c->id }}">
                                {{ trim($c->first_name . ' ' . $c->last_name) }}
                                @if ($c->document_number) · {{ $c->document_number }} @endif
                            </option>
                        @endforeach
                    </select>

                    <button type="button" class="mb mb--primary mb--block" id="posCobrar" disabled>
                        <x-mus.icon name="money" :w="16" stroke-width="2" />
                        {{ __('mus.acciones.cobrar') }} <span id="posCobrarTot"></span>
                        <kbd>F9</kbd>
                    </button>
                </div>
            </section>
        </div>

        {{-- ══════════ Barra de cobro fija (solo celular) ══════════ --}}
        <div class="pos-movil" id="posMovil">
            <button type="button" class="pos-movil__t" id="posMovilVer">
                <span id="posMovilN">{{ __('mus.pos.sin_productos') }}</span>
                <b id="posMovilTot">$0</b>
            </button>
            <button type="button" class="mb mb--primary" id="posMovilCobrar">
                <x-mus.icon name="money" :w="16" stroke-width="2" />
                {{ __('mus.acciones.cobrar') }}
            </button>
        </div>

        {{-- ══════════ Modal de cobro ══════════ --}}
        <div class="pos-modal" id="posModal" role="dialog" aria-modal="true" aria-label="{{ __('mus.pos.cobrar_aria') }}">
            <div class="pos-modal__box">
                <header>
                    <h3>{{ __('mus.acciones.cobrar') }}</h3>
                    <button type="button" id="posCerrar" aria-label="{{ __('mus.acciones.cerrar') }}">
                        <x-mus.icon name="close" :w="16" stroke-width="2.4" />
                    </button>
                </header>

                <div class="pos-cobro">
                    <div class="pos-cobro__tot">
                        <span>{{ __('mus.pos.total_a_cobrar') }}</span>
                        <b id="modalTotal">$0</b>
                    </div>

                    <div class="pos-cobro__medios" id="posMedios">
                        @foreach ($medios as $i => $m)
                            <label class="pos-medio">
                                <span class="pos-medio__n">{{ $m->name }}</span>
                                <span class="pos-medio__in">
                                    <i>$</i>
                                    <input type="number" step="1" min="0" inputmode="numeric"
                                           data-pago data-medio="{{ $m->id }}"
                                           name="pagos[{{ $i }}][amount]" value="" placeholder="0">
                                    <input type="hidden" name="pagos[{{ $i }}][payment_method_id]" value="{{ $m->id }}">
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <button type="button" class="mb mb--ghost mb--sm mb--block" id="posExacto">
                        {{ __('mus.pos.pago_exacto') }}
                    </button>

                    <div class="pos-cobro__res">
                        <div><span>{{ __('mus.pos.recibido') }}</span><b id="modalRecibido">$0</b></div>
                        <div><span>{{ __('mus.pos.falta') }}</span><b id="modalFalta" class="bad">$0</b></div>
                        <div><span>{{ __('mus.pos.cambio') }}</span><b id="modalCambio" class="ok">$0</b></div>
                    </div>

                    <textarea name="notes" rows="2" class="pos-notas"
                              placeholder="{{ __('mus.pos.nota_venta') }}"></textarea>
                </div>

                <footer>
                    <button type="button" class="mb mb--ghost" id="posVolver">{{ __('mus.acciones.volver') }}</button>
                    <button type="submit" class="mb mb--primary" id="posConfirmar" disabled>
                        <x-mus.icon name="check" :w="15" stroke-width="2.4" />
                        {{ __('mus.pos.confirmar_venta') }}
                    </button>
                </footer>
            </div>
        </div>

        <div id="posOcultos"></div>
    </form>

    @push('styles')
    @include('pos._estilos')
    @endpush

    @push('scripts')
    <script>
    window.POS_PRODUCTOS = @json($productos);
    </script>
    @include('pos._script')
    @endpush
</x-mus.page>
