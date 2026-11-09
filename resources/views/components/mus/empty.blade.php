@props(['icon' => 'box', 'title' => 'Todavía no hay nada aquí', 'text' => null])
<div class="mempty">
    <span class="mempty__ico"><x-mus.icon :name="$icon" :w="24" stroke-width="1.6" /></span>
    <h4>{{ $title }}</h4>
    @if ($text)<p>{{ $text }}</p>@endif
    @isset($action)
        <div style="margin-top:4px">{{ $action }}</div>
    @endisset
</div>
