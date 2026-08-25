<x-mus.page title="Punto de venta" subtitle="Atiende al cliente y cobra" icon="money">
    <x-slot name="actions">
        @if ($turno)
            <x-mus.btn href="{{ route('cash.show', $turno) }}" icon="clock">
                Turno abierto · base ${{ number_format((float) $turno->opening_amount, 0, ',', '.') }}
            </x-mus.btn>
        @elseif (Route::has('cash.create'))
            <x-mus.btn href="{{ route('cash.create') }}" variant="primary" icon="money">Abrir caja</x-mus.btn>
        @endif
        <x-mus.btn href="{{ route('sales.index') }}" icon="back">Ventas del día</x-mus.btn>
    </x-slot>

    @unless ($turno)
        <div class="pos-aviso" data-reveal>
            <span><x-mus.icon name="alert" :w="16" stroke-width="2.2" /></span>
            <div>
                <b>No tienes un turno de caja abierto.</b>
                <p>Puedes vender igual, pero la venta no quedará asociada a ningún arqueo.
                   Lo recomendable es abrir la caja con su base antes de empezar.</p>
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
                               placeholder="Nombre, SKU o código de barras…  (F2)">
                        <kbd>F2</kbd>
                    </div>
                    <span class="pos-cat__n" id="posCuenta"></span>
                </div>

                <div class="pos-grid" id="posGrid"></div>
                <div class="pos-vacio" id="posVacio" hidden>
                    <x-mus.icon name="search" :w="26" stroke-width="1.5" />
                    <p>Ningún producto coincide con la búsqueda.</p>
                </div>
            </section>

            {{-- ══════════ Derecha: carrito ══════════ --}}
            <section class="pos-tic" data-reveal="right">
                <header class="pos-tic__head">
                    <div>
                        <h3>Venta en curso</h3>
                        <p id="posResumen">Sin productos</p>
                    </div>
                    <button type="button" class="mb mb--ghost mb--sm" id="posVaciar">Vaciar</button>
                </header>

                <div class="pos-tic__body" id="posLineas"></div>

                <div class="pos-tic__empty" id="posTicVacio">
                    <x-mus.icon name="box" :w="26" stroke-width="1.5" />
                    <p>Toca un producto para agregarlo.</p>
                </div>

                <div class="pos-tot">
                    <div><span>Subtotal</span><b id="totSub">$0</b></div>
                    <div><span>Descuentos</span><b id="totDesc">$0</b></div>
                    <div><span>Impuestos</span><b id="totImp">$0</b></div>
                    <div class="pos-tot__big"><span>Total</span><b id="totTotal">$0</b></div>
                </div>

                <div class="pos-tic__foot">
                    <select name="customer_id" class="pos-sel" id="posCliente">
                        <option value="">Consumidor final</option>
                        @foreach ($clientes as $c)
                            <option value="{{ $c->id }}">
                                {{ trim($c->first_name . ' ' . $c->last_name) }}
                                @if ($c->document_number) · {{ $c->document_number }} @endif
                            </option>
                        @endforeach
                    </select>

                    <button type="button" class="mb mb--primary mb--block" id="posCobrar" disabled>
                        <x-mus.icon name="money" :w="16" stroke-width="2" />
                        Cobrar <span id="posCobrarTot"></span>
                        <kbd>F9</kbd>
                    </button>
                </div>
            </section>
        </div>

        {{-- ══════════ Barra de cobro fija (solo celular) ══════════ --}}
        <div class="pos-movil" id="posMovil">
            <button type="button" class="pos-movil__t" id="posMovilVer">
                <span id="posMovilN">Sin productos</span>
                <b id="posMovilTot">$0</b>
            </button>
            <button type="button" class="mb mb--primary" id="posMovilCobrar">
                <x-mus.icon name="money" :w="16" stroke-width="2" />
                Cobrar
            </button>
        </div>

        {{-- ══════════ Modal de cobro ══════════ --}}
        <div class="pos-modal" id="posModal" role="dialog" aria-modal="true" aria-label="Cobrar">
            <div class="pos-modal__box">
                <header>
                    <h3>Cobrar</h3>
                    <button type="button" id="posCerrar" aria-label="Cerrar">
                        <x-mus.icon name="close" :w="16" stroke-width="2.4" />
                    </button>
                </header>

                <div class="pos-cobro">
                    <div class="pos-cobro__tot">
                        <span>Total a cobrar</span>
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
                        Pago exacto en el primer medio
                    </button>

                    <div class="pos-cobro__res">
                        <div><span>Recibido</span><b id="modalRecibido">$0</b></div>
                        <div><span>Falta</span><b id="modalFalta" class="bad">$0</b></div>
                        <div><span>Cambio</span><b id="modalCambio" class="ok">$0</b></div>
                    </div>

                    <textarea name="notes" rows="2" class="pos-notas"
                              placeholder="Nota de la venta (opcional)"></textarea>
                </div>

                <footer>
                    <button type="button" class="mb mb--ghost" id="posVolver">Volver</button>
                    <button type="submit" class="mb mb--primary" id="posConfirmar" disabled>
                        <x-mus.icon name="check" :w="15" stroke-width="2.4" />
                        Confirmar venta
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
