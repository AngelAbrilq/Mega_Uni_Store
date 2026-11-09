{{-- Sub-navegación del módulo de nómina (RN-45). Cada pestaña según permiso. --}}
@php
    $u = auth()->user();
    $tabs = array_filter([
        ['payrolls.*', 'payrolls.index', 'Nóminas', 'receipt', $u->can('nomina.ver')],
        ['overtime.*', 'overtime.index', 'Horas extra', 'clock', $u->can('nomina.novedades')],
        ['leaves.*', 'leaves.index', 'Vacaciones y ausencias', 'calendar', $u->can('nomina.novedades')],
        ['benefits.*', 'benefits.index', 'Prestaciones', 'wallet', $u->can('nomina.ver')],
        ['contracts.*', 'contracts.index', 'Contratos', 'file', $u->can('nomina.ver')],
        ['payroll_concepts.*', 'payroll_concepts.index', 'Conceptos', 'layers', $u->can('nomina.gestionar')],
        ['payroll_report.*', 'payroll_report.index', 'Consolidado', 'chart', $u->can('nomina.consolidado')],
    ], fn ($t) => $t[4]);
@endphp
<nav class="nnav" aria-label="Nómina">
    @foreach ($tabs as [$patron, $ruta, $etiqueta, $icono])
        <a href="{{ route($ruta) }}" class="{{ request()->routeIs($patron) && ! request()->routeIs('payrolls.parametros*') ? 'is-on' : '' }}">
            <x-mus.icon :name="$icono" :w="15" /> {{ $etiqueta }}
        </a>
    @endforeach
    @can('nomina.gestionar')
        <a href="{{ route('payrolls.parametros') }}" class="nnav__fin {{ request()->routeIs('payrolls.parametros*') ? 'is-on' : '' }}" title="Parámetros legales">
            <x-mus.icon name="gear" :w="15" /> Parámetros
        </a>
    @endcan
</nav>

@once
@push('styles')
<style>
    /* ═══ Nómina · estilos compartidos ═══ */
    .nnav{ display:flex; gap:4px; flex-wrap:nowrap; overflow-x:auto; margin:-4px 0 16px; padding:4px; border:1px solid var(--line); border-radius:13px; background:var(--card); scrollbar-width:none; }
    .nnav::-webkit-scrollbar{ display:none; }
    .nnav a{ display:inline-flex; align-items:center; gap:7px; padding:8px 13px; border-radius:9px; white-space:nowrap; font-size:13px; font-weight:600; color:var(--ink-2); text-decoration:none; transition:background .15s, color .15s; }
    .nnav a:hover{ background:var(--paper); color:var(--ink); }
    .nnav a.is-on{ background:var(--a-600); color:#fff; }
    .nnav__fin{ margin-left:auto; }

    .nk{ display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:12px; margin-bottom:16px; }
    .nk__c{ padding:14px 16px; border-radius:14px; background:var(--card); border:1px solid var(--line); min-width:0; }
    .nk__c span{ display:block; font-size:12px; color:var(--muted); }
    .nk__c b{ display:block; margin-top:3px; font-size:21px; letter-spacing:-.02em; font-variant-numeric:tabular-nums; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .nk__c small{ display:block; margin-top:2px; font-size:11.6px; color:var(--muted); }
    .nk__c--main{ background:linear-gradient(135deg, var(--a-600), var(--a-500)); border-color:transparent; }
    .nk__c--main span, .nk__c--main small{ color:rgba(255,255,255,.82); }
    .nk__c--main b{ color:#fff; font-size:24px; }
    .nk__c--warn b{ color:#9a6511; }
    .nk__c--bad b{ color:var(--bad, #b91c1c); }
    a.nk__c{ text-decoration:none; transition:border-color .15s, transform .15s; }
    a.nk__c:hover{ border-color:var(--a-500); transform:translateY(-1px); }

    .nfil{ display:flex; align-items:center; gap:9px; flex-wrap:wrap; padding:12px 16px; border-bottom:1px solid var(--line-2); background:var(--paper); }
    .nfil label{ display:flex; align-items:center; gap:6px; font-size:12.4px; color:var(--muted); font-weight:600; }
    .nfil input, .nfil select{ height:36px; padding:0 10px; border:1px solid var(--line); border-radius:9px; font:inherit; font-size:13px; color:var(--ink); background:#fff; }
    .nfil select{ padding-right:30px; -webkit-appearance:none; appearance:none;
        background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2366768F' stroke-width='2.4' stroke-linecap='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 11px center; }
    .nfil__sp{ flex:1; }
    .ntabs{ display:flex; gap:4px; flex-wrap:wrap; }
    .ntabs a{ padding:6px 12px; border-radius:999px; font-size:12.8px; font-weight:600; color:var(--ink-2); text-decoration:none; }
    .ntabs a i{ font-style:normal; opacity:.6; margin-left:3px; }
    .ntabs a.is-on{ background:var(--a-600); color:#fff; }
    .ntabs a.is-on i{ opacity:.85; }

    .ngrid{ display:grid; gap:16px; grid-template-columns:minmax(0, 1.7fr) minmax(300px, 1fr); align-items:start; }
    .ngrid > *{ min-width:0; }
    @media (max-width:1060px){ .ngrid{ grid-template-columns:minmax(0,1fr); } }

    .nform{ display:grid; gap:12px; }
    .nform__2{ display:grid; gap:12px; grid-template-columns:1fr 1fr; }
    .nform label{ display:grid; gap:5px; font-size:12.4px; font-weight:600; color:var(--ink-2); }
    .nform input:not([type=checkbox]), .nform select, .nform textarea{ width:100%; height:40px; padding:0 11px; border:1px solid var(--line); border-radius:10px; font:inherit; font-size:13.4px; color:var(--ink); background:#fff; }
    .nform textarea{ height:auto; padding:9px 11px; }
    .nform input[type=file]{ padding:7px 9px; height:auto; }
    .nform small{ font-weight:500; color:var(--muted); font-size:11.6px; }
    .nform .nerr{ color:var(--bad, #b91c1c); font-weight:600; font-size:11.8px; }
    @media (max-width:560px){ .nform__2{ grid-template-columns:1fr; } .nform input:not([type=checkbox]), .nform select{ height:44px; font-size:16px; } }

    .nacc{ display:flex; gap:6px; align-items:center; justify-content:flex-end; flex-wrap:wrap; }
    .nacc form{ display:flex; gap:6px; align-items:center; margin:0; }
    .nacc input[type=text]{ height:30px; padding:0 9px; border:1px solid var(--line); border-radius:8px; font:inherit; font-size:12.4px; min-width:150px; background:var(--card); }
    @media (max-width:760px){ .nacc{ justify-content:flex-start; } .nacc input[type=text]{ flex:1; min-width:0; height:38px; font-size:16px; } }

    .nwho b{ display:block; font-size:13.3px; color:var(--ink); }
    .nwho small{ display:block; font-size:11.5px; color:var(--muted); }
    .nmon{ font-variant-numeric:tabular-nums; white-space:nowrap; }
    .ncode{ font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12px; font-weight:600; color:var(--ink-2); }

    .naviso{ display:flex; gap:12px; align-items:flex-start; margin-bottom:16px; padding:13px 15px; border-radius:12px; border:1px solid rgba(196,127,5,.35); background:rgba(245,165,36,.07); }
    .naviso > span{ display:grid; place-items:center; width:30px; height:30px; flex:none; border-radius:9px; background:#c47f05; color:#fff; }
    .naviso b{ display:block; font-size:13.4px; color:var(--ink); }
    .naviso p{ margin:2px 0 0; font-size:12.5px; color:var(--muted); line-height:1.5; }
    .naviso--info{ border-color:rgba(46,110,168,.3); background:rgba(46,110,168,.06); }
    .naviso--info > span{ background:var(--a-600); }
    .naviso--ok{ border-color:rgba(49,104,77,.3); background:rgba(49,104,77,.06); }
    .naviso--ok > span{ background:#31684D; }
    .naviso--bad{ border-color:rgba(163,48,43,.3); background:rgba(163,48,43,.06); }
    .naviso--bad > span{ background:#A3302B; }

    .nlist{ list-style:none; margin:0; padding:0; }
    .nlist li{ display:flex; gap:10px; align-items:center; justify-content:space-between; padding:11px 16px; border-top:1px solid var(--line-2); font-size:13px; }
    .nlist li:first-child{ border-top:0; }
    .nlist li > div{ min-width:0; }
    .nhint{ margin:0; padding:12px 16px; font-size:12.2px; color:var(--muted); line-height:1.55; border-top:1px solid var(--line-2); background:var(--paper); }
    .nbar{ height:8px; border-radius:999px; background:var(--line-2); overflow:hidden; }
    .nbar i{ display:block; height:100%; border-radius:inherit; background:var(--a-500); }
</style>
@endpush
@endonce
