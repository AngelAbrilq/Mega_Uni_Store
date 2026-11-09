@php
    use App\Support\Formato;
    $iconos = ['info' => 'info', 'warning' => 'alert', 'danger' => 'alert'];
@endphp

<x-mus.page title="Notificaciones" subtitle="{{ $noLeidas ? $noLeidas . ' sin leer' : 'Estás al día' }}" icon="info">
    <x-slot name="actions">
        @if ($noLeidas)
            <form method="POST" action="{{ route('notifications.leer-todas') }}">
                @csrf
                <x-mus.btn type="submit" icon="check">Marcar todas como leídas</x-mus.btn>
            </form>
        @endif
        @can('notificaciones.enviar')
            <x-mus.btn href="{{ route('notifications.create') }}" variant="primary" icon="plus">Enviar aviso</x-mus.btn>
        @endcan
    </x-slot>

    <x-mus.panel :pad="false" :reveal="true">
        <nav class="nfil" aria-label="Filtro">
            <a href="{{ route('notifications.index') }}" @class(['is-on' => ! $soloNoLeidas])>Todas</a>
            <a href="{{ route('notifications.index', ['no_leidas' => 1]) }}" @class(['is-on' => $soloNoLeidas])>Sin leer @if ($noLeidas)<b>{{ $noLeidas }}</b>@endif</a>
        </nav>

        @if ($notificaciones->total())
            <ul class="nlista">
                @foreach ($notificaciones as $n)
                    @php $d = $n->data; $nivel = $d['nivel'] ?? 'info'; @endphp
                    <li @class(['nitem', 'nitem--' . $nivel, 'is-nueva' => ! $n->read_at])>
                        <a href="{{ route('notifications.leer', $n->id) }}">
                            <span class="nitem__ico"><x-mus.icon :name="$iconos[$nivel] ?? 'info'" :w="16" /></span>
                            <span class="nitem__txt">
                                <b>{{ $d['titulo'] ?? 'Aviso' }}</b>
                                <em>{{ $d['mensaje'] ?? '' }}</em>
                                <i>
                                    {{ Formato::haceCuanto($n->created_at, true) }}
                                    @if (! empty($d['emisor'])) · {{ $d['emisor'] }} @endif
                                </i>
                            </span>
                            @unless ($n->read_at)<span class="nitem__punto" aria-label="Sin leer"></span>@endunless
                        </a>
                    </li>
                @endforeach
            </ul>
            <x-mus.pagination :items="$notificaciones" label="notificaciones" />
        @else
            <x-mus.empty icon="check" title="{{ $soloNoLeidas ? 'Nada sin leer' : 'Sin notificaciones' }}"
                         text="Aquí llegan los avisos de stock bajo y los mensajes de la administración." />
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        .nfil{ display:flex; gap:6px; padding:11px 14px; border-bottom:1px solid var(--line-2); background:var(--paper); }
        .nfil a{ display:inline-flex; align-items:center; gap:6px; height:32px; padding:0 13px; border-radius:999px;
                 font-size:12.8px; color:var(--muted); text-decoration:none; border:1px solid transparent; }
        .nfil a.is-on{ background:var(--card); border-color:var(--line); color:var(--ink); font-weight:600; }
        .nfil b{ min-width:18px; height:18px; padding:0 5px; border-radius:9px; background:var(--a-500); color:#fff;
                 font-size:10.5px; display:inline-grid; place-items:center; }
        .nlista{ list-style:none; margin:0; padding:0; }
        .nitem a{ display:flex; align-items:flex-start; gap:12px; padding:14px 16px; border-bottom:1px solid var(--line-2);
                  text-decoration:none; color:inherit; transition:background .18s var(--e-soft); }
        .nitem a:hover{ background:var(--paper); }
        .nitem.is-nueva a{ background:rgba(46,110,168,.05); }
        .nitem__ico{ display:grid; place-items:center; width:32px; height:32px; flex:none; border-radius:9px;
                     background:rgba(46,110,168,.12); color:var(--a-600); }
        .nitem--warning .nitem__ico{ background:rgba(150,112,60,.14); color:#96703C; }
        .nitem--danger .nitem__ico{ background:rgba(176,58,46,.12); color:var(--bad); }
        .nitem__txt{ flex:1; min-width:0; }
        .nitem__txt b{ display:block; font-size:13.4px; color:var(--ink); }
        .nitem.is-nueva .nitem__txt b{ font-weight:700; }
        .nitem__txt em{ display:block; font-style:normal; font-size:12.8px; color:var(--ink-2); margin-top:2px; line-height:1.5; }
        .nitem__txt i{ display:block; font-style:normal; font-size:11.4px; color:var(--muted-2); margin-top:4px; }
        .nitem__punto{ width:9px; height:9px; margin-top:6px; border-radius:50%; background:var(--a-500); flex:none; }
    </style>
    @endpush
</x-mus.page>
