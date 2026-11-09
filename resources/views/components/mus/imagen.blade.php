@props([
    'name'    => 'imagen',
    'label'   => 'Imagen',
    'actual'  => null,          // URL de la imagen que ya está guardada
    'hint'    => 'JPG, PNG o WEBP · máximo 2 MB',
    'wide'    => true,
    'borrar'  => true,          // ofrece la casilla para quitarla
])
@php
    $err = $errors->first($name);
    $id  = 'img_' . $name;
@endphp
<div class="{{ $wide ? 'mf__wide' : '' }} {{ $err ? 'mfld--err' : '' }}">
    <label class="mfld__lab" for="{{ $id }}">{{ $label }}</label>

    <div class="mimg" data-imagen>
        {{-- Vista previa: la actual, o la que se acaba de elegir --}}
        <div class="mimg__prev {{ $actual ? '' : 'is-empty' }}" data-prev>
            @if ($actual)
                <img src="{{ $actual }}" alt="" data-img>
            @else
                <img alt="" data-img hidden>
                <span class="mimg__ph" data-ph>
                    <x-mus.icon name="box" :w="22" stroke-width="1.5" />
                </span>
            @endif
        </div>

        <div class="mimg__side">
            <input type="file" id="{{ $id }}" name="{{ $name }}"
                   accept="image/jpeg,image/png,image/webp" data-file
                   {{ $attributes->merge(['class' => 'mimg__file']) }}>

            <label for="{{ $id }}" class="mb mb--ghost mb--sm mimg__btn">
                <x-mus.icon name="plus" :w="14" stroke-width="2" />
                {{ $actual ? 'Cambiar imagen' : 'Elegir imagen' }}
            </label>

            <p class="mimg__name" data-nombre>{{ $actual ? 'Imagen guardada' : 'Ningún archivo elegido' }}</p>

            @if ($borrar && $actual)
                <label class="mimg__del">
                    <input type="checkbox" name="{{ $name }}_eliminar" value="1">
                    <span>Quitar la imagen actual</span>
                </label>
            @endif

            @if ($err)
                <p class="mfld__err"><x-mus.icon name="alert" :w="12" stroke-width="2.2" /><span>{{ $err }}</span></p>
            @else
                <p class="mfld__hint">{{ $hint }}</p>
            @endif
        </div>
    </div>
</div>

<style>
    .mimg{ display:flex; gap:16px; align-items:flex-start; padding:14px;
           border:1px solid var(--line); border-radius:12px; background:var(--paper); }
    .mimg__prev{ position:relative; width:104px; height:104px; flex:none; overflow:hidden;
                 border:1px solid var(--line); border-radius:11px; background:#fff;
                 display:grid; place-items:center; }
    /* Anclada a los cuatro lados: una foto muy alta o muy ancha se
       recorta y se centra, nunca estira ni desborda la vista previa. */
    .mimg__prev img{ position:absolute; inset:0; width:100%; height:100%;
                     object-fit:cover; object-position:center; display:block; }
    .mimg__ph{ position:absolute; inset:0; display:grid; place-items:center; color:var(--muted-2); }
    .mimg__prev.is-empty{ background:repeating-linear-gradient(45deg,#fff,#fff 8px,var(--line-2) 8px,var(--line-2) 16px); }

    .mimg__side{ min-width:0; flex:1; }
    .mimg__file{ position:absolute; width:1px; height:1px; opacity:0; pointer-events:none; }
    .mimg__btn{ cursor:pointer; }
    .mimg__name{ margin:8px 0 0; font-size:12.2px; color:var(--ink-2);
                 overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .mimg__del{ display:flex; align-items:center; gap:7px; margin-top:9px;
                font-size:12.2px; color:var(--bad); cursor:pointer; user-select:none; }
    .mimg__del input{ accent-color:var(--bad); }
</style>

@once
@push('scripts')
<script>
/* Vista previa inmediata al elegir un archivo, sin subirlo todavía. */
document.addEventListener('change', function (ev) {
    var input = ev.target.closest('[data-file]');
    if (!input) return;

    var caja   = input.closest('[data-imagen]');
    var prev   = caja.querySelector('[data-prev]');
    var img    = caja.querySelector('[data-img]');
    var ph     = caja.querySelector('[data-ph]');
    var nombre = caja.querySelector('[data-nombre]');
    var f      = input.files && input.files[0];

    if (!f) {
        nombre.textContent = 'Ningún archivo elegido';
        return;
    }

    nombre.textContent = f.name + ' · ' + (f.size / 1024).toFixed(0) + ' KB';

    var lector = new FileReader();
    lector.onload = function (e) {
        img.src = e.target.result;
        img.hidden = false;
        if (ph) ph.style.display = 'none';
        prev.classList.remove('is-empty');
    };
    lector.readAsDataURL(f);
});
</script>
@endpush
@endonce
