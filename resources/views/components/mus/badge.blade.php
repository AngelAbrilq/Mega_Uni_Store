@props(['tone' => 'soft', 'dot' => false])
<span {{ $attributes->merge(['class' => 'mbg mbg--' . $tone]) }}>
    @if ($dot)<i></i>@endif{{ $slot }}
</span>
