@php use App\Support\Formato; @endphp

<x-mus.page title="Editar" :subtitle="$recurso->nombre" icon="users"
            :crumbs="['Quién atiende' => route('recursos.index'), 'Editar' => null]">

    <form method="POST" action="{{ route('recursos.update', $recurso) }}" novalidate>
        @csrf
        @method('PUT')
        <x-mus.panel title="Datos" sub="Cómo se llama y cuándo trabaja">
            @include('recursos.form', ['item' => $recurso])
            <x-slot name="foot">
                <x-mus.btn href="{{ route('recursos.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar cambios</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>

    {{-- ══════════ Bloqueos ══════════ --}}
    <x-mus.panel title="Cuándo no está" sub="Vacaciones, festivos, una diligencia" :reveal="true">
        @if ($recurso->bloqueos->count())
            <ul class="mblo">
                @foreach ($recurso->bloqueos as $b)
                    <li>
                        <span>
                            <b>{{ Formato::enPalabras($b->inicio, 'D MMM, h:mm a') }}</b>
                            a {{ Formato::enPalabras($b->fin, 'D MMM, h:mm a') }}
                            @if ($b->motivo)<em>{{ $b->motivo }}</em>@endif
                        </span>
                        <form method="POST" action="{{ route('recursos.desbloquear', [$recurso, $b]) }}">
                            @csrf @method('DELETE')
                            <x-mus.btn type="submit" variant="ghost" :sm="true">Quitar</x-mus.btn>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('recursos.bloquear', $recurso) }}" class="mblo__nuevo">
            @csrf
            <label><span>Desde</span><input type="datetime-local" name="inicio" required></label>
            <label><span>Hasta</span><input type="datetime-local" name="fin" required></label>
            <label class="mblo__motivo"><span>Motivo</span>
                <input type="text" name="motivo" maxlength="150" placeholder="Vacaciones · Cita médica · Festivo"></label>
            <x-mus.btn type="submit" icon="plus">Bloquear</x-mus.btn>
        </form>

        <p class="mblo__pista">
            El bloqueo va aparte del horario a propósito. Si se editara el horario cada vez que
            alguien se enferma un martes, habría que acordarse de devolverlo — y nadie se acuerda:
            el horario queda mal para siempre.
        </p>
    </x-mus.panel>

    @push('styles')
    <style>
        .mblo{ list-style:none; margin:0 0 16px; padding:0; }
        .mblo li{ display:flex; align-items:center; justify-content:space-between; gap:12px;
                  padding:9px 0; border-bottom:1px solid var(--line-2); font-size:12.8px; }
        .mblo li:last-child{ border-bottom:0; }
        .mblo b{ color:var(--ink); }
        .mblo em{ display:block; font-style:normal; font-size:11.4px; color:var(--muted); }
        .mblo__nuevo{ display:flex; gap:11px; align-items:flex-end; flex-wrap:wrap; }
        .mblo__nuevo label{ display:block; min-width:0; }
        .mblo__nuevo span{ display:block; margin-bottom:5px; font-size:12px; color:var(--ink-2); }
        .mblo__nuevo input{ padding:9px 12px; border-radius:9px; border:1px solid var(--line);
                            background:var(--card); color:var(--ink); font-size:12.8px; font-family:inherit; }
        .mblo__nuevo input:focus{ outline:none; border-color:var(--a-500); }
        .mblo__motivo{ flex:1 1 220px; }
        .mblo__motivo input{ width:100%; }
        .mblo__pista{ margin:16px 0 0; max-width:74ch; font-size:11.8px; line-height:1.6; color:var(--muted); }
    </style>
    @endpush
</x-mus.page>
