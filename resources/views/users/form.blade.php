@php
    /**
     * Formulario compartido por crear y editar.
     * $item es null al crear y el usuario al editar.
     */
    $descripciones = [
        'Superadministrador' => 'Acceso total, incluidos los parámetros del sistema.',
        'Administrador'      => 'Gestiona todo el negocio: catálogo, terceros y usuarios.',
        'Supervisor'         => 'Consulta todo y edita catálogo, pero no elimina ni toca usuarios.',
        'Vendedor'           => 'Atiende el mostrador: consulta productos y gestiona clientes.',
        'Bodeguero'          => 'Recibe mercancía y ajusta existencias.',
        'Reportero'          => 'Solo lectura: informes y consultas.',
    ];

    $actuales = (array) (old('roles') ?? ($item?->roles->pluck('name')->all() ?? []));

    /* Para que el detalle del rol se lea en español y no en nombres de código. */
    $etiquetasModulo = [
        'productos' => 'Productos', 'categorias' => 'Categorías', 'clientes' => 'Clientes',
        'proveedores' => 'Proveedores', 'unidades' => 'Unidades', 'impuestos' => 'Impuestos',
        'medios_pago' => 'Medios de pago', 'atributos' => 'Atributos', 'usuarios' => 'Usuarios',
        'ventas' => 'Ventas', 'compras' => 'Compras', 'caja' => 'Caja',
        'inventario' => 'Inventario', 'panel' => 'Panel', 'reportes' => 'Reportes',
        'auditoria' => 'Auditoría', 'roles' => 'Roles', 'configuracion' => 'Configuración',
    ];

    $nombresAccion = [
        'ver' => 'ver', 'crear' => 'crear', 'editar' => 'editar', 'eliminar' => 'eliminar',
        'anular' => 'anular', 'devolver' => 'devolver', 'abrir' => 'abrir', 'cerrar' => 'cerrar',
        'ajustar' => 'ajustar', 'recibir' => 'recibir', 'gestionar' => 'gestionar',
    ];
@endphp

<div class="mf">
    <div class="mf__grid">
        <x-mus.imagen name="foto" label="Foto de perfil" :actual="$item?->imagen"
                      hint="Opcional · JPG, PNG o WEBP · máximo 2 MB. Se recorta en círculo sola." />

        <x-mus.field name="name" label="Nombre completo" :value="$item?->name" :required="true"
                     :wide="true" max="255" />

        <x-mus.field name="email" label="Correo electrónico" type="email" :value="$item?->email"
                     :required="true" max="255" :wide="true"
                     hint="Con este correo inicia sesión. Va en minúsculas." />

        <x-mus.field name="password" label="{{ $item ? 'Nueva contraseña' : 'Contraseña' }}"
                     type="password" :required="! $item"
                     hint="{{ $item ? 'Déjala vacía para no cambiarla.' : 'Mínimo 8 caracteres.' }}"
                     autocomplete="new-password" />

        <x-mus.field name="password_confirmation" label="Repetir contraseña"
                     type="password" :required="! $item"
                     autocomplete="new-password" />
    </div>
</div>

<div class="urol">
    <div class="urol__head">
        <span class="urol__ico"><x-mus.icon name="shield" :w="15" /></span>
        <div>
            <b>Roles asignados</b>
            <span>Definen a qué módulos entra y qué puede hacer en cada uno.</span>
        </div>
    </div>

    @error('roles')
        <p class="mfld__err" style="margin:0 0 10px">
            <x-mus.icon name="alert" :w="12" stroke-width="2.2" /><span>{{ $message }}</span>
        </p>
    @enderror

    <div class="urol__grid">
        @foreach ($roles as $rol)
            @php
                /* «productos.crear» → módulo «productos», acción «crear» */
                $porModulo = [];
                foreach ($rol->permissions as $permiso) {
                    $partes = explode('.', $permiso->name);
                    $porModulo[$partes[0]][] = $partes[1] ?? 'ver';
                }
                ksort($porModulo);
            @endphp

            {{-- La ventanita va FUERA del <label>: si estuviera dentro,
                 leerla o desplazarla marcaría el rol sin querer. --}}
            <div class="urol__box">
                <label class="urol__card">
                    <input type="checkbox" name="roles[]" value="{{ $rol->name }}"
                           @checked(in_array($rol->name, $actuales, true))>
                    <span class="urol__mark"><x-mus.icon name="check" :w="12" stroke-width="3" /></span>
                    <span class="urol__txt">
                        <b>{{ $rol->name }}</b>
                        <span>{{ $descripciones[$rol->name] ?? 'Rol personalizado.' }}</span>
                        <i class="urol__n" tabindex="0" role="button"
                           aria-label="Ver los permisos de {{ $rol->name }}">
                            {{ $rol->permissions_count ?? $rol->permissions()->count() }} permisos
                        </i>
                    </span>
                </label>

                <div class="urol__pop" role="tooltip">
                    <span class="urol__pop__t">
                        {{ $rol->name }} · {{ count($porModulo) }} módulos
                    </span>
                    <div class="urol__pop__l">
                        @foreach ($porModulo as $modulo => $acciones)
                            <div class="urol__pop__f">
                                <em>{{ $etiquetasModulo[$modulo] ?? ucfirst(str_replace('_', ' ', $modulo)) }}</em>
                                <b>{{ implode(' · ', array_map(fn ($a) => $nombresAccion[$a] ?? $a, $acciones)) }}</b>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<style>
    .urol{ margin-top:6px; padding:16px 18px 18px; border:1px solid var(--line);
           border-radius:12px; background:var(--paper); }
    .urol__head{ display:flex; align-items:flex-start; gap:11px; margin-bottom:14px; }
    .urol__ico{ display:grid; place-items:center; width:30px; height:30px; flex:none;
                border-radius:9px; background:var(--a-500); color:#fff; }
    .urol__head b{ display:block; font-size:13.5px; color:var(--ink); letter-spacing:-.01em; }
    .urol__head span span{ display:block; font-size:12.2px; color:var(--muted); margin-top:1px; }

    .urol__grid{ display:grid; gap:10px; grid-template-columns:repeat(auto-fill,minmax(232px,1fr)); }

    .urol__card{ position:relative; display:flex; gap:11px; padding:13px 14px; cursor:pointer;
                 border:1px solid var(--line); border-radius:11px; background:var(--card);
                 transition:border-color .18s var(--e-soft), box-shadow .18s var(--e-soft),
                            transform .18s var(--e-soft); }
    .urol__card:hover{ border-color:var(--a-400); transform:translateY(-1px); }
    .urol__card input{ position:absolute; opacity:0; pointer-events:none; }

    .urol__mark{ display:grid; place-items:center; width:19px; height:19px; flex:none; margin-top:1px;
                 border:1.6px solid var(--line); border-radius:6px; background:#fff;
                 color:transparent; transition:all .18s var(--e-soft); }
    .urol__card input:checked ~ .urol__mark{ background:var(--a-500); border-color:var(--a-500); color:#fff; }
    .urol__card:has(input:checked){ border-color:var(--a-500);
                                    box-shadow:0 0 0 3px rgba(46,110,168,.13); }

    .urol__txt b{ display:block; font-size:13px; color:var(--ink); }
    .urol__txt span{ display:block; font-size:11.9px; color:var(--muted); line-height:1.45; margin-top:2px; }
    .urol__txt i{ display:inline-block; margin-top:6px; font-style:normal; font-size:10.7px;
                  letter-spacing:.06em; text-transform:uppercase; color:var(--a-600); font-weight:700; }

    /* ── Detalle del rol al pasar el mouse ──
       El número de permisos no dice nada por sí solo; al apoyarse encima
       se despliega qué puede hacer exactamente ese rol, módulo por módulo.
       Funciona igual con el teclado (Tab hasta el número). */
    .urol__n{ cursor:help; border-bottom:1px dashed currentColor; padding-bottom:1px;
              transition:color .18s ease; }
    .urol__n:hover, .urol__n:focus-visible{ color:var(--a-500); outline:none; }

    .urol__box{ position:relative; }
    .urol__box .urol__card{ width:100%; }

    .urol__pop{
        position:absolute; left:0; right:0; top:calc(100% - 4px); z-index:40;
        display:block; padding:11px 13px 12px;
        background:var(--n-850); color:#fff; border-radius:10px;
        box-shadow:0 22px 46px -18px rgba(5,15,30,.8);
        opacity:0; transform:translateY(-6px) scale(.985); pointer-events:none;
        transition:opacity .2s ease, transform .26s var(--e-soft);
    }
    /* Pequeño triángulo que apunta a la tarjeta */
    .urol__pop::before{
        content:''; position:absolute; top:-5px; left:22px;
        width:10px; height:10px; background:inherit; transform:rotate(45deg);
    }
    .urol__box:hover .urol__pop,
    .urol__box:focus-within .urol__pop{
        opacity:1; transform:none; pointer-events:auto;
    }

    .urol__pop__t{
        display:block; font-size:10px; letter-spacing:.09em; text-transform:uppercase;
        color:var(--a-300); font-weight:700; margin-bottom:8px;
    }
    .urol__pop__l{ display:block; max-height:230px; overflow-y:auto;
                   scrollbar-width:thin; scrollbar-color:rgba(255,255,255,.22) transparent; }
    .urol__pop__l::-webkit-scrollbar{ width:5px; }
    .urol__pop__l::-webkit-scrollbar-thumb{ background:rgba(255,255,255,.22); border-radius:3px; }

    /* El módulo arriba en versalitas y las acciones debajo: cabe en el
       ancho de la tarjeta sin partir palabras y se lee de un vistazo. */
    .urol__pop__f{ padding:6px 0; border-top:1px solid rgba(255,255,255,.08); }
    .urol__pop__f:first-child{ border-top:0; padding-top:0; }
    .urol__pop__f em{
        display:block; font-style:normal; font-size:9.6px; font-weight:700;
        letter-spacing:.13em; text-transform:uppercase; color:#7C8FA8;
    }
    .urol__pop__f b{
        display:block; margin-top:2px;
        font-size:11.6px; font-weight:600; color:#fff; line-height:1.4;
    }

    /* En pantalla angosta se abre a lo ancho de la tarjeta, sin apretarse */
    @media (max-width:760px){
        .urol__pop__l{ max-height:260px; }
    }
</style>
