@props([
    'name',
    'label',
    'type'     => 'text',
    'value'    => null,
    'required' => false,
    'hint'     => null,
    'prefix'   => null,
    'wide'     => false,
    'step'     => null,
    'max'      => null,
])
@php $err = $errors->first($name); @endphp
<div class="{{ $wide ? 'mf__wide' : '' }} {{ $err ? 'mfld--err' : '' }}">
    <label class="mfld__lab" for="f_{{ $name }}">
        {{ $label }}
        @if ($required)<span class="mfld__req">*</span>@endif
    </label>
    <div class="mfld__box">
        @if ($prefix)<span class="mfld__pre">{{ $prefix }}</span>@endif
        <input id="f_{{ $name }}" name="{{ $name }}" type="{{ $type }}"
               value="{{ old($name, $value) }}"
               @if ($required) required @endif
               @if ($step) step="{{ $step }}" @endif
               @if ($max) maxlength="{{ $max }}" @endif
               {{ $attributes->merge(['class' => 'mfld__in']) }}>
    </div>
    @if ($err)
        <p class="mfld__err"><x-mus.icon name="alert" :w="12" stroke-width="2.2" /><span>{{ $err }}</span></p>
    @elseif ($hint)
        <p class="mfld__hint">{{ $hint }}</p>
    @endif
</div>
