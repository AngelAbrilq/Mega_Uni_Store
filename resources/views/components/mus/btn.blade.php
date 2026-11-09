@props([
    'href'    => null,
    'variant' => 'ghost',
    'icon'    => null,
    'iconEnd' => null,
    'sm'      => false,
    'block'   => false,
    'type'    => 'button',
])
@php
    $cls = 'mb mb--' . $variant . ($sm ? ' mb--sm' : '') . ($block ? ' mb--block' : '');
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }}>
        @if ($icon)<x-mus.icon :name="$icon" :w="$sm ? 14 : 15" stroke-width="2" />@endif
        {{ $slot }}
        @if ($iconEnd)<x-mus.icon :name="$iconEnd" :w="$sm ? 14 : 15" stroke-width="2.2" />@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $cls]) }}>
        @if ($icon)<x-mus.icon :name="$icon" :w="$sm ? 14 : 15" stroke-width="2" />@endif
        {{ $slot }}
        @if ($iconEnd)<x-mus.icon :name="$iconEnd" :w="$sm ? 14 : 15" stroke-width="2.2" />@endif
    </button>
@endif
