{{--
    Entrada a la prueba de 2 h 30.

    Se pide lo mínimo que hace falta para armarle el negocio y devolverle la
    llamada: cómo se llama el negocio, cómo se llama él, un correo, un
    teléfono opcional y qué clase de negocio tiene. Cada campo de más en
    esta pantalla es gente que no la termina.

    El rubro no es un desplegable sino tarjetas: es la decisión que cambia
    todo lo que va a ver adentro, y merece verse entera de un vistazo en vez
    de esconderse detrás de un clic.
--}}
<x-guest-layout>
    <div class="mdemo">
        <header class="mdemo__cab">
            <span class="mdemo__reloj">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9"/><path d="M12 7.2V12l3.2 2"/>
                </svg>
                {{ intdiv($minutos, 60) }} h {{ $minutos % 60 }} min de prueba
            </span>
            <h1>Prueba el sistema con tu propio negocio</h1>
            <p>
                Escoge qué clase de negocio tienes y te lo montamos con su catálogo,
                sus cargos y sus productos. Entras de una y puedes vender en el
                primer minuto. No pedimos tarjeta.
            </p>
        </header>

        <form method="POST" action="{{ route('demo.guardar') }}" novalidate>
            @csrf

            {{-- ══════════ Rubro ══════════ --}}
            <fieldset class="mdemo__rubros">
                <legend>¿Qué clase de negocio tienes?</legend>

                <div class="mdemo__grid">
                    @foreach ($rubros as $slug => [$nombre, $descripcion])
                        <label class="mdemo__r">
                            <input type="radio" name="rubro" value="{{ $slug }}"
                                   @checked(old('rubro') === $slug)>
                            <span>
                                <b>{{ $nombre }}</b>
                                <em>{{ $descripcion }}</em>
                            </span>
                        </label>
                    @endforeach
                </div>

                @error('rubro')<p class="mdemo__err">{{ $message }}</p>@enderror
            </fieldset>

            {{-- ══════════ Datos ══════════ --}}
            <div class="mdemo__campos">
                <label>
                    <span>Nombre del negocio</span>
                    <input type="text" name="negocio" value="{{ old('negocio') }}"
                           placeholder="Ferretería El Tornillo" required maxlength="120">
                    @error('negocio')<i>{{ $message }}</i>@enderror
                </label>

                <label>
                    <span>Tu nombre</span>
                    <input type="text" name="nombre" value="{{ old('nombre') }}"
                           placeholder="William Toles" required maxlength="120">
                    @error('nombre')<i>{{ $message }}</i>@enderror
                </label>

                <label>
                    <span>Correo</span>
                    <input type="email" name="email" value="{{ old('email') }}"
                           placeholder="tucorreo@ejemplo.com" required maxlength="150"
                           autocomplete="username">
                    @error('email')<i>{{ $message }}</i>@enderror
                </label>

                <label>
                    <span>WhatsApp <small>opcional</small></span>
                    <input type="tel" name="telefono" value="{{ old('telefono') }}"
                           placeholder="300 000 0000" maxlength="30">
                    @error('telefono')<i>{{ $message }}</i>@enderror
                </label>

                <label>
                    <span>Contraseña</span>
                    <input type="password" name="password" required autocomplete="new-password"
                           placeholder="Mínimo 8 caracteres">
                    @error('password')<i>{{ $message }}</i>@enderror
                </label>

                <label>
                    <span>Repite la contraseña</span>
                    <input type="password" name="password_confirmation" required
                           autocomplete="new-password">
                </label>
            </div>

            <button type="submit" class="mdemo__ir">
                Entrar a probarlo
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </button>

            <p class="mdemo__nota">
                Al terminar las {{ intdiv($minutos, 60) }} h {{ $minutos % 60 }} min la prueba se
                cierra sola y los datos de ejemplo se borran. Si te sirve, hablamos y te montamos
                el tuyo de verdad, con tus productos.
            </p>
        </form>
    </div>

    <style>
        /* La tarjeta del layout de invitado mide 448 px, que es lo correcto
           para un formulario de ingreso de dos campos. Esta pantalla tiene
           seis campos y seis tarjetas de rubro, y a ese ancho queda una
           columna larguísima que hay que desplazar entera.

           Se ensancha desde aquí con `:has`, que es lo que permite que una
           regla escrita adentro alcance al contenedor de afuera. Donde el
           navegador no lo soporte no pasa nada malo: las rejillas son
           `auto-fit`, colapsan a una columna y la pantalla sigue
           funcionando — más larga, pero entera. */
        @supports selector(:has(*)) {
            body:has(.mdemo) .mus-card{ max-width:min(780px, 100%); }
        }

        /* ── Paleta ──
           El fondo de la página es oscuro pero la tarjeta es BLANCA. Los
           colores de aquí son los de la tarjeta, no los del fondo: escribir
           en claro sobre claro es como se hace ilegible una pantalla sin
           que nadie lo note hasta verla. */
        .mdemo{
            --d-ink:#17212F; --d-ink-2:#33465E; --d-muted:#6B7C92;
            --d-line:#DFE5EC; --d-line-2:#EDF1F6;
            --d-a:#2E6EA8;  --d-a-2:#4A8FC9;  --d-bad:#96504F;
            color:var(--d-ink-2);
        }

        .mdemo__cab{ margin-bottom:22px; }
        .mdemo__reloj{ display:inline-flex; align-items:center; gap:6px; padding:4px 11px;
                       border:1px solid rgba(46,110,168,.28); border-radius:99px;
                       font-size:11.6px; color:var(--d-a); background:rgba(46,110,168,.06); }
        .mdemo__reloj svg{ width:13px; height:13px; }
        .mdemo__cab h1{ margin:12px 0 7px; font-size:22px; line-height:1.25; font-weight:650;
                        color:var(--d-ink); text-wrap:balance; }
        .mdemo__cab p{ margin:0; max-width:62ch; font-size:13.2px; line-height:1.62;
                       color:var(--d-muted); }

        .mdemo__rubros{ border:0; padding:0; margin:0 0 20px; }
        .mdemo__rubros legend{ padding:0; margin-bottom:9px; font-size:11px; font-weight:700;
                               letter-spacing:.07em; text-transform:uppercase; color:var(--d-muted); }

        /* minmax(0,…) por dentro del min(): con `1fr` a secas una descripción
           larga ensancha su columna y las tarjetas dejan de medir lo mismo. */
        .mdemo__grid{ display:grid; grid-template-columns:repeat(auto-fit, minmax(min(170px,100%), 1fr));
                      gap:8px; }
        .mdemo__r{ display:block; cursor:pointer; }
        .mdemo__r input{ position:absolute; opacity:0; width:0; height:0; }
        .mdemo__r span{ display:block; height:100%; padding:11px 13px; border-radius:11px;
                        border:1px solid var(--d-line); background:#F8FAFC;
                        transition:border-color .18s ease, background .18s ease; }
        .mdemo__r:hover span{ border-color:#B9C6D6; }
        .mdemo__r input:focus-visible + span{ outline:2px solid var(--d-a-2); outline-offset:2px; }
        .mdemo__r input:checked + span{ border-color:var(--d-a-2); background:rgba(46,110,168,.08);
                                        box-shadow:inset 0 0 0 1px var(--d-a-2); }
        .mdemo__r b{ display:block; font-size:13px; font-weight:600; color:var(--d-ink); }
        .mdemo__r em{ display:block; margin-top:2px; font-style:normal; font-size:11.4px;
                      line-height:1.45; color:var(--d-muted); }

        .mdemo__campos{ display:grid; grid-template-columns:repeat(auto-fit, minmax(min(210px,100%), 1fr));
                        gap:13px; }
        .mdemo__campos label{ display:block; min-width:0; }
        .mdemo__campos > label > span{ display:block; margin-bottom:5px; font-size:12px;
                                       color:var(--d-ink-2); }
        .mdemo__campos small{ color:var(--d-muted); font-size:10.6px; }
        /* La tarjeta ya le da estilo a text/email/password. Aquí se completa
           lo que falta —el ancho y el tipo `tel`, que su regla no cubre— sin
           volver a declarar colores que ya están bien puestos. */
        .mdemo__campos input{ width:100%; border:1px solid var(--d-line); border-radius:8px;
                              background:#F6F8FB; padding:12px 14px;
                              color:var(--d-ink); font-size:13.6px; font-family:inherit; }
        .mdemo__campos input:focus{ outline:none; border-color:var(--d-a-2); background:#fff;
                                    box-shadow:0 0 0 4px rgba(74,143,201,.13); }
        .mdemo__campos input::placeholder{ color:#9BAABC; }
        .mdemo__campos i{ display:block; margin-top:4px; font-style:normal; font-size:11.4px;
                          color:var(--d-bad); }
        .mdemo__err{ margin:7px 0 0; font-size:11.8px; color:var(--d-bad); }

        .mdemo__ir{ display:flex; align-items:center; justify-content:center; gap:8px;
                    width:100%; margin-top:20px; padding:13px 18px; border:0; border-radius:9px;
                    background:linear-gradient(135deg,var(--d-a),var(--d-a-2)); color:#fff;
                    font-size:14.4px; font-weight:600; font-family:inherit; cursor:pointer;
                    transition:filter .18s ease, transform .18s ease; }
        .mdemo__ir:hover{ filter:brightness(1.07); }
        .mdemo__ir:active{ transform:translateY(1px); }
        .mdemo__ir svg{ width:17px; height:17px; }

        .mdemo__nota{ margin:14px 0 0; max-width:66ch; font-size:11.8px; line-height:1.6;
                      color:var(--d-muted); }
    </style>
</x-guest-layout>
