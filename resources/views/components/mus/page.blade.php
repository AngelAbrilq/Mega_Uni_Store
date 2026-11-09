@props([
    'title',
    'subtitle' => null,
    'icon'     => 'tag',
    'crumbs'   => [],
])
<x-app-layout>
    <x-slot name="header">
        <div class="mh">
            <div class="mh__l">
                <span class="mh__ico"><x-mus.icon :name="$icon" :w="18" /></span>
                <div class="mh__t">
                    @if (count($crumbs))
                        <div class="mcrumb">
                            @foreach ($crumbs as $texto => $url)
                                @if ($url)
                                    <a href="{{ $url }}">{{ $texto }}</a>
                                    <x-mus.icon name="next" :w="11" />
                                @else
                                    <span>{{ $texto }}</span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                    <h1>{{ $title }}</h1>
                    @if ($subtitle)
                        <p>{{ $subtitle }}</p>
                    @endif
                </div>
            </div>
            @isset($actions)
                <div class="mh__r">{{ $actions }}</div>
            @endisset
        </div>
    </x-slot>

    {{ $slot }}
</x-app-layout>
