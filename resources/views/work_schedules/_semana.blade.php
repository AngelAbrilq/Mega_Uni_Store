{{-- La semana de un horario: una fila por día, con sus franjas. --}}
@php $porDia = $franjas->groupBy('weekday'); @endphp
<div class="sem">
    @foreach (\App\Models\WorkSchedule::DIAS as $n => $dia)
        <div class="sem__d {{ $porDia->has($n) ? '' : 'is-libre' }}">
            <span>{{ mb_substr($dia, 0, 3) }}</span>
            <div>
                @forelse ($porDia->get($n, collect()) as $f)
                    <i>{{ $f->rango }}</i>
                @empty
                    <em>Libre</em>
                @endforelse
            </div>
        </div>
    @endforeach
</div>
@once
@push('styles')
<style>
    .sem{ display:grid; grid-template-columns:repeat(7, minmax(0,1fr)); gap:6px; }
    .sem__d{ display:flex; flex-direction:column; gap:5px; padding:8px 6px; border-radius:10px; background:rgba(46,110,168,.07); text-align:center; min-width:0; }
    .sem__d > span{ font-size:11px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:var(--a-700, #1f4f7a); }
    .sem__d i{ display:block; font-style:normal; font-size:11.5px; font-weight:600; color:var(--ink); font-variant-numeric:tabular-nums; white-space:nowrap; }
    .sem__d em{ font-style:normal; font-size:11px; color:var(--muted); }
    .sem__d.is-libre{ background:var(--paper); }
    .sem__d.is-libre > span{ color:var(--muted); }
    @media (max-width:640px){ .sem{ grid-template-columns:repeat(4, minmax(0,1fr)); } }
</style>
@endpush
@endonce
