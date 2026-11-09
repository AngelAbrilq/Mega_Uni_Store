@props(['name' => 'is_active', 'label' => 'Activo', 'hint' => 'Aparece disponible en el sistema', 'checked' => true, 'wide' => true])
<div class="{{ $wide ? 'mf__wide' : '' }}">
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="msw">
        <input type="checkbox" name="{{ $name }}" value="1" @checked((bool) old($name, $checked))>
        <span class="msw__track"></span>
        <span class="msw__txt">
            <b>{{ $label }}</b>
            @if ($hint)<span>{{ $hint }}</span>@endif
        </span>
    </label>
</div>
