@php
    use App\Models\Plan;
    $actuales = old('funciones', $plan?->funciones ?? ['pos', 'inventario', 'caja', 'reportes']);
    $limite   = fn ($campo) => old($campo, $plan ? ($plan->{$campo} ?? '') : '');
@endphp
<div class="mf">
    <div class="mf__grid">
        <x-mus.field name="nombre" label="Nombre del plan" :value="$plan?->nombre" :required="true" max="80"
                     hint="Como lo verá el cliente: Básico, Pro, Empresa…" />

        <x-mus.field name="slug" label="Identificador" :value="$plan?->slug" max="90"
                     hint="Opcional · se arma con el nombre. Va en enlaces y no debería cambiar." />

        <x-mus.field name="descripcion" label="Descripción" :value="$plan?->descripcion" :wide="true" max="200"
                     hint="Una línea para la página de precios." />
    </div>

    <h4 class="pln-sec">Precio</h4>
    <div class="mf__grid">
        <x-mus.field name="precio_mensual" label="Precio mensual" type="number" step="1" min="0" prefix="$"
                     :required="true" :value="$plan?->precio_mensual !== null ? (int) $plan?->precio_mensual : null" />

        <x-mus.field name="precio_anual" label="Precio anual" type="number" step="1" min="0" prefix="$"
                     :value="$plan?->precio_anual !== null ? (int) $plan?->precio_anual : null"
                     hint="Vacío = 12 veces el mensual. Es una decisión comercial, no una cuenta." />

        <x-mus.field name="precio_tienda_extra" label="Precio por local extra" type="number" step="1" min="0" prefix="$"
                     :value="$plan?->precio_tienda_extra !== null ? (int) $plan?->precio_tienda_extra : null"
                     hint="Por cada local contratado por encima de los que incluye." />

        <x-mus.field name="dias_prueba" label="Días de prueba" type="number" step="1" min="0" max="90"
                     :value="$plan?->dias_prueba ?? 0" />
    </div>

    @if ($plan && ($suscritos ?? 0) > 0)
        <p class="pln-aviso">
            <x-mus.icon name="info" :w="14" />
            <span>{{ $suscritos }} {{ $suscritos === 1 ? 'negocio tiene' : 'negocios tienen' }} este plan.
                Si cambias el precio, conservan el que pagan hoy; el nuevo aplica a las suscripciones que vengan.</span>
        </p>
    @endif

    <h4 class="pln-sec">Límites <small>vacío o 0 = sin límite</small></h4>
    <div class="mf__grid pln-lim">
        <x-mus.field name="max_tiendas" label="Locales incluidos" type="number" step="1" min="0" :value="$limite('max_tiendas')" placeholder="Sin límite" />
        <x-mus.field name="max_usuarios" label="Usuarios" type="number" step="1" min="0" :value="$limite('max_usuarios')" placeholder="Sin límite" />
        <x-mus.field name="max_productos" label="Productos" type="number" step="1" min="0" :value="$limite('max_productos')" placeholder="Sin límite" />
        <x-mus.field name="max_ventas_mes" label="Ventas al mes" type="number" step="1" min="0" :value="$limite('max_ventas_mes')" placeholder="Sin límite" />
    </div>

    <h4 class="pln-sec">Funciones incluidas</h4>
    @error('funciones.*')<p class="mfld__err">{{ $message }}</p>@enderror
    <div class="pln-fun">
        @foreach (Plan::FUNCIONES as $clave => $etiqueta)
            <label class="pln-chip">
                <input type="checkbox" name="funciones[]" value="{{ $clave }}" @checked(in_array($clave, $actuales, true))>
                <span><x-mus.icon name="check" :w="12" stroke-width="3" /> {{ $etiqueta }}</span>
            </label>
        @endforeach
    </div>

    <h4 class="pln-sec">Publicación</h4>
    <div class="mf__grid">
        <x-mus.field name="orden" label="Orden en la página de precios" type="number" step="1" min="0"
                     :value="$plan?->orden ?? 0" />
        <div></div>
        <x-mus.toggle name="visible" label="Visible en la página de precios"
                      hint="Apagado: se puede asignar desde el panel pero no aparece publicado (plan a la medida)."
                      :checked="$plan?->visible ?? true" />
        <x-mus.toggle name="is_active" label="Plan activo"
                      hint="Inactivo: no se vende ni se asigna. Los negocios que ya lo tienen siguen igual."
                      :checked="$plan?->is_active ?? true" />
    </div>
</div>

@push('styles')
<style>
    .pln-sec{ margin:22px 0 10px; font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--n-500, #6b7280); }
    .pln-sec small{ font-weight:600; text-transform:none; letter-spacing:0; margin-left:6px; opacity:.8; }
    .pln-aviso{ display:flex; gap:8px; align-items:flex-start; margin:12px 0 0; padding:10px 12px; border-radius:10px;
                background:rgba(46,110,168,.07); color:var(--a-700, #1f4f7a); font-size:12.5px; line-height:1.45; }
    .pln-aviso svg{ flex:none; margin-top:2px; }
    .pln-lim{ grid-template-columns:repeat(4, minmax(0,1fr)); }
    @media (max-width:900px){ .pln-lim{ grid-template-columns:repeat(2, minmax(0,1fr)); } }
    .pln-fun{ display:flex; flex-wrap:wrap; gap:8px; }
    .pln-chip input{ position:absolute; opacity:0; pointer-events:none; }
    .pln-chip span{ display:inline-flex; align-items:center; gap:6px; padding:7px 12px; border-radius:999px; cursor:pointer;
                    border:1px solid rgba(15,23,42,.14); font-size:13px; font-weight:600; color:var(--n-600, #4b5563);
                    background:var(--card, #fff); transition:background .15s, border-color .15s, color .15s; }
    .pln-chip span svg{ opacity:0; width:0; transition:opacity .15s, width .15s; }
    .pln-chip input:checked + span{ border-color:var(--a-500, #2e6ea8); background:rgba(46,110,168,.09); color:var(--a-700, #1f4f7a); }
    .pln-chip input:checked + span svg{ opacity:1; width:12px; }
    .pln-chip input:focus-visible + span{ outline:2px solid var(--a-400, #5b8fc2); outline-offset:2px; }
</style>
@endpush
