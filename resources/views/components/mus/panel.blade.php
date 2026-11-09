@props(['title' => null, 'sub' => null, 'pad' => true, 'reveal' => true])
<section {{ $attributes->merge(['class' => 'mp']) }}
         @if ($reveal) data-reveal data-replay @endif>
    @if ($title || isset($tools))
        <header class="mp__head">
            @if ($title)
                <div class="mp__tit">
                    <h3>{{ $title }}</h3>
                    @if ($sub)<p>{{ $sub }}</p>@endif
                </div>
            @endif
            @isset($tools)
                {{-- Con clase y no con estilo en línea: así la regla de
                     celular puede apilar las herramientas. --}}
                <div class="mp__tools">{{ $tools }}</div>
            @endisset
        </header>
    @endif

    @if ($pad)
        <div class="mp__body">{{ $slot }}</div>
    @else
        {{ $slot }}
    @endif

    @isset($foot)
        <footer class="mp__foot">{{ $foot }}</footer>
    @endisset
</section>
