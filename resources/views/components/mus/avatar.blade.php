@props([
    'src'    => null,      // URL de la foto, o null
    'name'   => '',        // de aquí salen las iniciales
    'size'   => 34,        // lado del cuadro, en píxeles
    'round'  => false,     // true = círculo (personas), false = cuadro redondeado
    'color'  => null,      // fondo cuando no hay foto
    'letras' => null,      // iniciales ya calculadas (accesor $iniciales)
])
@php
    if ($letras === null) {
        $palabras = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letras = $palabras === []
            ? '?'
            : mb_strtoupper(mb_substr($palabras[0], 0, 1))
              . (count($palabras) > 1 ? mb_strtoupper(mb_substr($palabras[1], 0, 1)) : '');
    }

    $lado  = (int) $size;
    $radio = $round ? '50%' : max(6, (int) round($lado * 0.26)) . 'px';
    $fondo = $color ?: 'var(--a-600)';
@endphp
{{--
    El cuadro tiene lado fijo y la foto va con object-fit:cover: recorta
    lo que sobra y centra el resto, así que una foto vertical, horizontal
    o cuadrada —JPG, PNG o WEBP— siempre llena el espacio sin estirarse.
    No se deforma nunca porque la imagen jamás se escala en un solo eje.
--}}
<span {{ $attributes->merge([
        'class' => 'mav',
        'style' => "--av:{$lado}px;--avr:{$radio};--avc:{$fondo}",
    ]) }}>
    @if ($src)
        <img src="{{ $src }}" alt="" loading="lazy" decoding="async">
    @else
        <i>{{ $letras }}</i>
    @endif
</span>
