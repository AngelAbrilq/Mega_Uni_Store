{{--
    TBL-SES-001 · Lista de sesiones. Recibe:
      $sesiones  colección de UserSession
      $actual    id de la sesión de esta petición (o null)
      $propias   true en «Mi perfil» (botones por sesión); false en la ficha de usuario
--}}
@php
    use App\Support\Formato;
    use App\Models\UserSession;
    $iconos = [
        'escritorio' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        'celular'    => '<rect x="7" y="2.5" width="10" height="19" rx="2.4"/><path d="M11 18.5h2"/>',
        'tableta'    => '<rect x="4.5" y="3" width="15" height="18" rx="2.4"/><path d="M11 18h2"/>',
    ];
@endphp
@if ($sesiones->count())
    <ul class="ses">
        @foreach ($sesiones as $s)
            @php $viva = $s->vigente(); @endphp
            <li class="{{ $viva ? '' : 'is-off' }}">
                <span class="ses__ico">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $iconos[$s->device_type] ?? $iconos['escritorio'] !!}</svg>
                </span>
                <div class="ses__txt">
                    <b>
                        {{ $s->device ?? 'Dispositivo' }}
                        @if ($s->id === $actual)<x-mus.badge tone="ok" :dot="true">Esta sesión</x-mus.badge>@endif
                    </b>
                    <span>
                        IP {{ $s->ip_address ?? '—' }} ·
                        @if ($viva)
                            activa {{ Formato::enPalabras($s->last_activity_at, 'D MMM, HH:mm', '') }} · vence {{ $s->expires_at->format('H:i') }}
                        @else
                            {{ UserSession::MOTIVOS[$s->end_reason] ?? 'Vencida' }} {{ Formato::enPalabras($s->ended_at ?? $s->expires_at, 'D MMM, HH:mm', '') }}
                        @endif
                    </span>
                </div>
                @if ($propias && $viva)
                    <form method="POST" action="{{ route('sesiones.destroy', $s) }}">
                        @csrf @method('DELETE')
                        <x-mus.btn type="submit" :sm="true" icon="close">{{ $s->id === $actual ? 'Salir' : 'Cerrar' }}</x-mus.btn>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>
@else
    <p class="sub" style="margin:0">No hay sesiones abiertas.</p>
@endif

@once
@push('styles')
<style>
    .ses{ list-style:none; margin:0; padding:0; display:grid; gap:2px; }
    .ses li{ display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--line-2); }
    .ses li:last-child{ border-bottom:0; }
    .ses li.is-off{ opacity:.55; }
    .ses__ico{ width:36px; height:36px; flex:none; border-radius:10px; display:grid; place-items:center;
               background:rgba(46,110,168,.08); color:var(--a-600); }
    .ses li.is-off .ses__ico{ background:var(--paper); color:var(--muted); }
    .ses__txt{ flex:1; min-width:0; }
    .ses__txt b{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; font-size:13.4px; color:var(--ink); }
    .ses__txt span{ display:block; font-size:11.8px; color:var(--muted); margin-top:2px; }
</style>
@endpush
@endonce
