@php
    $sistema = (bool) $permiso?->is_system;
    $modActual = old('module', $permiso?->module);
@endphp
<div class="mf">
    @if ($sistema)
        <p class="prm-nota">
            <x-mus.icon name="info" :w="14" />
            <span>Este permiso lo usa el código (<code>{{ $permiso->name }}</code>): su nombre no cambia. Solo se edita la descripción.</span>
        </p>
        <input type="hidden" name="module" value="{{ $permiso->module }}">
        <input type="hidden" name="action" value="{{ $permiso->action }}">
    @endif

    <div class="mf__grid">
        @unless ($sistema)
            <x-mus.select name="module" label="Módulo" :value="$modActual" :options="$modulos" empty="— Escoge uno o escribe uno nuevo —" />

            <x-mus.field name="modulo_nuevo" label="…o módulo nuevo" :value="null" max="40"
                         hint="Solo letras, números y guion bajo: se normaliza solo." />

            <x-mus.field name="action" label="Acción" :value="$permiso?->action" :required="true" max="40"
                         hint="ver = lectura · exportar = exportación · lo demás = escritura." />

            <div></div>
        @endunless

        <x-mus.field name="description" label="Descripción" :value="$permiso?->description" :wide="true" max="200"
                     hint="Para qué sirve, en palabras de quien asigna roles." />

        @unless ($permiso)
            <x-mus.toggle name="para_administrador" label="Dárselo al Administrador de cada negocio"
                          hint="Así cada dueño puede repartirlo en sus roles. El Superadministrador lo recibe siempre."
                          :checked="true" />
        @endunless
    </div>

    <p class="prm-nombre">Se guardará como <code id="prmNombre">{{ $permiso?->name ?? 'modulo.accion' }}</code></p>
</div>

@unless ($sistema)
<script>
(function () {
    var m = document.getElementById('f_module'), n = document.getElementById('f_modulo_nuevo'),
        a = document.getElementById('f_action'), o = document.getElementById('prmNombre');
    function limpiar(v){ return (v || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, ''); }
    function pintar(){ o.textContent = (limpiar(n.value) || limpiar(m.value) || 'modulo') + '.' + (limpiar(a.value) || 'accion'); }
    [m, n, a].forEach(function (el) { el && el.addEventListener('input', pintar); el && el.addEventListener('change', pintar); });
})();
</script>
@endunless

@push('styles')
<style>
    .prm-nota{ display:flex; gap:8px; align-items:flex-start; margin:0 0 14px; padding:10px 12px; border-radius:10px;
               background:rgba(46,110,168,.07); color:var(--a-700, #1f4f7a); font-size:12.5px; }
    .prm-nota svg{ flex:none; margin-top:2px; }
    .prm-nombre{ margin:14px 0 0; font-size:12.5px; color:var(--muted); }
    .prm-nombre code, .prm-nota code{ font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-weight:700; color:var(--ink); }
</style>
@endpush
