@php
    /**
     * Sidebar vertical del panel.
     * Cada entrada: [ruta, etiqueta, icono, permiso].
     * Una entrada se pinta solo si la ruta existe Y el usuario tiene el
     * permiso: así el menú nunca ofrece una puerta que va a dar 403.
     */
    $grupos = [
        __('menu.grupos.principal') => [
            ['dashboard',       __('menu.panel'),        'grid',    'panel.ver'],
            ['pos.index',       __('menu.vender'),       'money',   'ventas.crear'],
            ['sales.index',     __('menu.ventas'),       'receipt', 'ventas.ver'],
            ['cash.index',      __('menu.caja'),         'wallet',  'caja.ver'],
            ['returns.index',   __('menu.devoluciones'), 'back',    'ventas.ver'],
            ['reports.index',   __('menu.reportes'),     'trend',   'reportes.ver'],
        ],
        __('menu.grupos.catalogo') => [
            ['products.index',   __('menu.productos'),  'box',    'productos.ver'],
            ['categories.index', __('menu.categorias'), 'layers', 'categorias.ver'],
            ['inventory.index',  __('menu.inventario'), 'stock',  'inventario.ver'],
            ['traslados.index',  __('menu.traslados'),  'back',   'inventario.ver'],
        ],
        __('menu.grupos.comercial') => [
            ['customers.index', __('menu.clientes'),    'users', 'clientes.ver'],
            ['suppliers.index', __('menu.proveedores'), 'truck', 'proveedores.ver'],
            ['purchases.index', __('menu.compras'),     'cart',  'compras.ver'],
        ],
        __('menu.grupos.configuracion') => [
            ['units.index',           __('menu.unidades'),    'ruler',   'unidades.ver'],
            ['taxes.index',           __('menu.impuestos'),   'percent', 'impuestos.ver'],
            ['payment_methods.index', __('menu.medios_pago'), 'card',    'medios_pago.ver'],
            ['attributes.index',      __('menu.atributos'),   'tag',     'atributos.ver'],
        ],
        __('menu.grupos.administracion') => [
            ['users.index',    __('menu.usuarios'),  'shield', 'usuarios.ver'],
            ['roles.index',    __('menu.roles'),     'roles',  'roles.gestionar'],
            ['audit.index',    __('menu.auditoria'), 'key',    'auditoria.ver'],
            ['plan.index',     __('menu.plan'),      'card',   'configuracion.editar'],
            ['sistema.index',  __('menu.sistema'),   'globo',  'sistema.superadmin'],
            ['settings.edit',  __('menu.ajustes'),   'gear',   'configuracion.editar'],
        ],
    ];

    $iconos = [
        'grid'   => '<rect x="3.2" y="3.2" width="7.2" height="7.2" rx="2"/><rect x="13.6" y="3.2" width="7.2" height="7.2" rx="2"/><rect x="3.2" y="13.6" width="7.2" height="7.2" rx="2"/><rect x="13.6" y="13.6" width="7.2" height="7.2" rx="2"/>',
        'box'    => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
        'users'  => '<circle cx="9" cy="8" r="3.4"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M17 8.2a3 3 0 0 1 0 5.6M18 20a5.6 5.6 0 0 0-2-4.3"/>',
        'truck'  => '<path d="M2 7h11v10H2z"/><path d="M13 10h4l4 3.5V17h-8z"/><circle cx="6.5" cy="18.5" r="1.8"/><circle cx="17" cy="18.5" r="1.8"/>',
        'ruler'  => '<rect x="2.5" y="8.5" width="19" height="7" rx="1.6"/><path d="M7 8.5v3M11 8.5v4.5M15 8.5v3M19 8.5v4.5"/>',
        'percent'=> '<path d="M19 5 5 19"/><circle cx="7.5" cy="7.5" r="2.5"/><circle cx="16.5" cy="16.5" r="2.5"/>',
        'card'   => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6 15h4"/>',
        'tag'    => '<path d="M20.5 12.5 12 21l-9-9V3h9z"/><circle cx="7.5" cy="7.5" r="1.6"/>',
        'shield' => '<path d="M12 3 4.5 6v6c0 4.4 3.1 7.9 7.5 9 4.4-1.1 7.5-4.6 7.5-9V6z"/><path d="m9 12 2.2 2.2L15.4 10"/>',
        'roles'  => '<path d="M12 3 4.5 6v6c0 4.4 3.1 7.9 7.5 9 4.4-1.1 7.5-4.6 7.5-9V6z"/><circle cx="12" cy="11" r="2.2"/><path d="M8.6 17a3.6 3.6 0 0 1 6.8 0"/>',
        'globo'  => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 2.5 15.4 0 18M12 3c-2.5 2.6-2.5 15.4 0 18"/>',
        'money'  => '<rect x="2.5" y="6" width="19" height="12" rx="2.5"/><circle cx="12" cy="12" r="2.6"/><path d="M6 12h.01M18 12h.01"/>',
        'receipt'=> '<path d="M5 3.5h14v17l-2.3-1.6-2.3 1.6-2.4-1.6-2.4 1.6L7.3 19 5 20.5z"/><path d="M8.5 8h7M8.5 12h7M8.5 15.5h4"/>',
        'wallet' => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H18v3"/><rect x="3" y="7.5" width="18" height="12" rx="2.5"/><circle cx="16.5" cy="13.5" r="1.4"/>',
        'stock'  => '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/>',
        'key'    => '<circle cx="7.5" cy="14.5" r="3.5"/><path d="m10.2 12.2 8-8M16.2 6.2l2.2 2.2M14 8.4l2.2 2.2"/>',
        'trend'  => '<path d="M3 17.5 9.5 11l4 4L21 7.5"/><path d="M15.5 7.5H21v5.5"/>',
        'gear'   => '<circle cx="12" cy="12" r="3.2"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 9 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 9a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>',
        'back'   => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'cart'   => '<circle cx="9.5" cy="19" r="1.6"/><circle cx="17.5" cy="19" r="1.6"/><path d="M2.5 3.5h2.6l2.3 11.2h11.1l1.8-8H6.2"/>',
    ];

    $usuario = auth()->user();

    /** ¿Se le pinta esta entrada a quien está mirando? */
    $puede = function (array $entrada) use ($usuario): bool {
        if (! Route::has($entrada[0])) {
            return false;
        }

        $permiso = $entrada[3] ?? null;

        return $permiso === null || ($usuario?->can($permiso) ?? false);
    };
@endphp

<aside class="mus-side" id="musSide">

    {{-- ══════════ Cabecera ══════════ --}}
    <div class="mus-side__head">
        <a href="{{ route('dashboard') }}" class="mus-logo">
            <span class="mus-logo__mark">
                <svg viewBox="0 0 48 48" fill="none" stroke="#fff" stroke-width="2.8"
                     stroke-linejoin="round" stroke-linecap="round">
                    <path d="M8 16 L24 7 L40 16 L24 25 Z"/>
                    <path d="M8 16 v16 L24 41 V25"/>
                    <path d="M40 16 v16 L24 41"/>
                </svg>
            </span>
            <span class="mus-logo__text">
                <b>MEGA UNI STORE</b>
                <i>{{ __('mus.ui.panel') }}</i>
            </span>
        </a>

        {{-- Fija el menú abierto; si no está fijado se despliega solo al pasar el mouse. --}}
        <button class="mus-pin" id="musPin" type="button" aria-label="{{ __('mus.ui.fijar_menu') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 6l-6 6 6 6"/>
            </svg>
        </button>
    </div>

    {{-- ══════════ Usuario ══════════
         Va arriba, pegado a la marca: es lo primero que uno quiere
         confirmar al entrar («¿con qué cuenta estoy?»). --}}
    <div class="mus-side__me">
        <button class="mus-user" id="musUserBtn" type="button" aria-label="{{ __('mus.ui.menu_usuario') }}">
            <x-mus.avatar :src="$usuario->imagen" :name="$usuario->name" :letras="$usuario->iniciales"
                          :color="$usuario->color_avatar" :size="38" :round="true" class="mus-user__av" />
            <span class="mus-user__info">
                <b>{{ $usuario->name }}</b>
                <span>{{ $usuario->getRoleNames()->first() ?? $usuario->email }}</span>
            </span>
            <svg class="mus-user__caret" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </button>

        <div class="mus-menu" id="musUserMenu">
            <a href="{{ route('profile.edit') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="8" r="3.4"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>
                </svg>
                {{ __('mus.acciones.mi_perfil') }}
            </a>
            <a href="{{ route('dashboard') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                     stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3.2" y="3.2" width="7.2" height="7.2" rx="2"/>
                    <rect x="13.6" y="3.2" width="7.2" height="7.2" rx="2"/>
                    <rect x="3.2" y="13.6" width="7.2" height="7.2" rx="2"/>
                    <rect x="13.6" y="13.6" width="7.2" height="7.2" rx="2"/>
                </svg>
                {{ __('mus.acciones.ir_al_panel') }}
            </a>

            <hr>

            {{-- ══════════ Idioma ══════════
                 Cada botón es su propio formulario en POST: cambiar el
                 idioma escribe en el perfil del usuario, y una dirección
                 que escribe algo con solo abrirla se dispara sola con
                 cualquier precarga del navegador. --}}
            <div class="mus-lang">
                <span class="mus-lang__lab">{{ __('mus.ui.idioma') }}</span>
                <div class="mus-lang__ops">
                    @foreach (\App\Http\Middleware\EstableceIdioma::DISPONIBLES as $codigo => $nombre)
                        <form method="POST" action="{{ route('idioma.cambiar') }}" data-sin-ctx>
                            @csrf
                            <input type="hidden" name="idioma" value="{{ $codigo }}">
                            <button type="submit"
                                    class="mus-lang__b {{ app()->getLocale() === $codigo ? 'is-on' : '' }}"
                                    @if (app()->getLocale() === $codigo) aria-current="true" @endif>
                                {{ mb_strtoupper($codigo) }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>

            <hr>

            <button type="button" class="danger" data-logout-trigger>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 4h3.5A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5H15"/>
                    <path d="M11 12H4M7.5 8.5 4 12l3.5 3.5"/>
                </svg>
                {{ __('mus.acciones.cerrar_sesion') }}
            </button>
        </div>

        <form method="POST" action="{{ route('logout') }}" id="musLogoutForm" data-logout data-sin-ctx hidden>
            @csrf
        </form>
    </div>

    {{-- ══════════ Navegación ══════════ --}}
    <nav class="mus-nav">
        @foreach ($grupos as $titulo => $entradas)
            @php
                $visibles = array_values(array_filter($entradas, $puede));
            @endphp
            @if (count($visibles))
                <div class="mus-nav__label"><span>{{ $titulo }}</span></div>
                <div class="mus-nav__rule" aria-hidden="true"></div>

                @foreach ($visibles as [$ruta, $texto, $icono, $permiso])
                    @php
                        $base   = \Illuminate\Support\Str::before($ruta, '.');
                        $activo = request()->routeIs($ruta) || request()->routeIs($base . '.*');
                    @endphp
                    <a href="{{ route($ruta) }}"
                       class="mus-item {{ $activo ? 'is-active' : '' }}"
                       data-tip="{{ $texto }}"
                       @if ($activo) aria-current="page" @endif>
                        <span class="mus-item__ico">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                {!! $iconos[$icono] ?? $iconos['tag'] !!}
                            </svg>
                        </span>
                        <span class="mus-item__txt">{{ $texto }}</span>
                    </a>
                @endforeach
            @endif
        @endforeach
    </nav>

</aside>
