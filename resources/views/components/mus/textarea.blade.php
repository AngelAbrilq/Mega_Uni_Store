@props(['name', 'label', 'value' => null, 'required' => false, 'hint' => null, 'wide' => true, 'rows' => 4])
@php $err = $errors->first($name); @endphp
<div class="{{ $wide ? 'mf__wide' : '' }} {{ $err ? 'mfld--err' : '' }}">
    <label class="mfld__lab" for="f_{{ $name }}">
        {{ $label }}
        @if ($required)<span class="mfld__req">*</span>@endif
    </label>
    <div class="mfld__box">
        <textarea id="f_{{ $name }}" name="{{ $name }}" rows="{{ $rows }}"
                  @if ($required) required @endif
                  {{ $attributes->merge(['class' => 'mfld__in']) }}>{{ old($name, $value) }}</textarea>
    </div>
    @if ($err)
        <p class="mfld__err"><x-mus.icon name="alert" :w="12" stroke-width="2.2" /><span>{{ $err }}</span></p>
    @elseif ($hint)
        <p class="mfld__hint">{{ $hint }}</p>
    @endif
</div>
