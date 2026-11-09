@props([
    'name', 'label',
    'options'  => [],
    'value'    => null,
    'required' => false,
    'hint'     => null,
    'empty'    => null,
    'wide'     => false,
])
@php $err = $errors->first($name); $actual = old($name, $value); @endphp
<div class="{{ $wide ? 'mf__wide' : '' }} {{ $err ? 'mfld--err' : '' }}">
    <label class="mfld__lab" for="f_{{ $name }}">
        {{ $label }}
        @if ($required)<span class="mfld__req">*</span>@endif
    </label>
    <div class="mfld__box">
        <select id="f_{{ $name }}" name="{{ $name }}"
                @if ($required) required @endif
                {{ $attributes->merge(['class' => 'mfld__in']) }}>
            @if ($empty)
                <option value="">{{ $empty }}</option>
            @endif
            @foreach ($options as $k => $v)
                <option value="{{ $k }}" @selected((string) $actual === (string) $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    @if ($err)
        <p class="mfld__err"><x-mus.icon name="alert" :w="12" stroke-width="2.2" /><span>{{ $err }}</span></p>
    @elseif ($hint)
        <p class="mfld__hint">{{ $hint }}</p>
    @endif
</div>
