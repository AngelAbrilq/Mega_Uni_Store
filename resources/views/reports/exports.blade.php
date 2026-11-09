@php
    use App\Support\Formato;
    $tonoEnvio = ['pendiente' => 'warn', 'enviado' => 'ok', 'fallido' => 'bad'];
@endphp

<x-mus.page title="Exportar reportes" subtitle="PDF o Excel, con historial de archivos y envíos" icon="trend"
            :crumbs="['Reportes' => route('reports.index'), 'Exportar' => null]">

    @if (session('whatsapp'))
        <div class="xaviso" role="status">
            <x-mus.icon name="share" :w="16" />
            <span>El mensaje con el enlace de descarga está listo.</span>
            <a href="{{ session('whatsapp') }}" target="_blank" rel="noopener" class="mb mb--primary mb--sm">Abrir WhatsApp</a>
        </div>
    @endif
    @if (session('descargar'))
        <div class="xaviso" role="status">
            <x-mus.icon name="check" :w="16" />
            <span>El archivo quedó guardado en el historial.</span>
            <a href="{{ route('reports.exports.descargar', session('descargar')) }}" class="mb mb--primary mb--sm">Descargar ahora</a>
        </div>
    @endif

    @if ($errors->has('recipient'))
        <div class="xaviso xaviso--bad" role="alert"><x-mus.icon name="alert" :w="16" /><span>{{ $errors->first('recipient') }}</span></div>
    @endif

    {{-- ══════════ Generar ══════════ --}}
    <x-mus.panel title="Nuevo archivo" sub="Las cifras son las mismas de la pantalla de reportes, del local activo">
        <form method="POST" action="{{ route('reports.exports.store') }}" class="xgen" novalidate>
            @csrf
            <div class="mf__grid">
                <x-mus.select name="report" label="Reporte" :required="true" :wide="true"
                              :options="$reportes" :value="old('report', 'ventas_dia')" />
                <x-mus.field name="desde" label="Desde" type="date" :required="true" :value="$rango['desde']" />
                <x-mus.field name="hasta" label="Hasta" type="date" :required="true" :value="$rango['hasta']" />
            </div>
            <fieldset class="xfmt">
                <legend>Formato</legend>
                <label class="xfmt__o"><input type="radio" name="format" value="pdf" @checked(old('format', 'pdf') === 'pdf')>
                    <span><b>PDF</b><em>Para imprimir o mandar a gerencia</em></span></label>
                <label class="xfmt__o"><input type="radio" name="format" value="xlsx" @checked(old('format') === 'xlsx')>
                    <span><b>Excel (.xlsx)</b><em>Cifras como números, listas para sumar</em></span></label>
            </fieldset>
            @error('format')<p class="mfld__err"><x-mus.icon name="alert" :w="12" /><span>{{ $message }}</span></p>@enderror
            <div class="xgen__pie">
                <x-mus.btn type="submit" variant="primary" icon="save">Generar archivo</x-mus.btn>
            </div>
        </form>
    </x-mus.panel>

    {{-- ══════════ Historial de exportaciones ══════════ --}}
    <x-mus.panel title="Archivos generados" :pad="false" :sub="$exportes->total() . ' en total'">
        @if ($exportes->total())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr><th>Reporte</th><th>Formato</th><th>Periodo</th><th class="num">Filas</th><th class="num">Tamaño</th><th>Generado</th><th class="num">Envíos</th><th class="act"></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($exportes as $x)
                            <tr data-row>
                                <td><b>{{ $reportes[$x->report] ?? $x->report }}</b><span class="sub" style="display:block">{{ $x->user?->nombre_completo ?? '—' }}</span></td>
                                <td><x-mus.badge :tone="$x->format === 'pdf' ? 'bad' : 'ok'">{{ strtoupper($x->format) }}</x-mus.badge></td>
                                <td class="sub">{{ $x->filters['desde'] ?? '' }} → {{ $x->filters['hasta'] ?? '' }}</td>
                                <td class="num">{{ number_format($x->rows, 0, ',', '.') }}</td>
                                <td class="num">{{ $x->tamano }}</td>
                                <td>{{ Formato::enPalabras($x->created_at, 'D MMM, HH:mm', '—') }}</td>
                                <td class="num">{{ $x->deliveries_count }}</td>
                                <td class="act">
                                    <span class="mt__acts">
                                        <x-mus.btn href="{{ route('reports.exports.descargar', $x) }}" variant="ghost" :sm="true" class="mb--icon" title="Descargar">
                                            <x-mus.icon name="back" :w="15" style="transform:rotate(-90deg)" />
                                        </x-mus.btn>
                                        <button type="button" class="mb mb--ghost mb--sm" data-enviar="{{ $x->id }}"
                                                data-nombre="{{ $x->file_name }}">Enviar</button>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-mus.pagination :items="$exportes" label="archivos" />
        @else
            <x-mus.empty icon="trend" title="Todavía no hay archivos" text="Genera el primero con el formulario de arriba." />
        @endif
    </x-mus.panel>

    {{-- ══════════ Historial de envíos (TBL-ENV-001) ══════════ --}}
    <x-mus.panel title="Envíos" :pad="false" sub="Los 30 más recientes">
        @if ($envios->count())
            <div class="mt-wrap">
                <table class="mt">
                    <thead><tr><th>Canal</th><th>Destinatario</th><th>Archivo</th><th>Estado</th><th>Enviado</th><th>Quién</th></tr></thead>
                    <tbody>
                        @foreach ($envios as $e)
                            <tr>
                                <td>{{ $e->channel === 'email' ? 'Correo' : 'WhatsApp' }}</td>
                                <td>{{ $e->recipient }}</td>
                                <td class="sub">{{ $e->export?->file_name }}</td>
                                <td>
                                    <x-mus.badge :tone="$tonoEnvio[$e->status] ?? 'soft'" :dot="true">{{ ucfirst($e->status) }}</x-mus.badge>
                                    @if ($e->error)<span class="sub" style="display:block">{{ $e->error }}</span>@endif
                                </td>
                                <td>{{ $e->sent_at ? Formato::enPalabras($e->sent_at, 'D MMM, HH:mm') : '—' }}</td>
                                <td>{{ $e->user?->nombre_completo ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-mus.empty icon="share" title="Sin envíos" text="Usa «Enviar» en un archivo para mandarlo por correo o WhatsApp." />
        @endif
    </x-mus.panel>

    {{-- ══════════ Ventana de envío ══════════ --}}
    <div class="vmodal" id="xModal" role="dialog" aria-modal="true" aria-labelledby="xModalT">
        <form method="POST" class="vmodal__box" id="xForm" novalidate>
            @csrf
            <h3 id="xModalT">Enviar archivo</h3>
            <p id="xModalN" class="sub"></p>
            <fieldset class="xfmt">
                <legend>Canal</legend>
                <label class="xfmt__o"><input type="radio" name="channel" value="email" checked><span><b>Correo</b><em>Va como adjunto</em></span></label>
                <label class="xfmt__o"><input type="radio" name="channel" value="whatsapp"><span><b>WhatsApp</b><em>Enlace de descarga por 7 días</em></span></label>
            </fieldset>
            <label style="margin-top:11px;display:block">
                <span id="xDestL">Correo del destinatario</span>
                <input type="text" name="recipient" id="xDest" required maxlength="150" autocomplete="off" placeholder="gerencia@negocio.com">
            </label>
            @error('recipient')<p class="mfld__err"><x-mus.icon name="alert" :w="12" /><span>{{ $message }}</span></p>@enderror
            <div class="vmodal__foot">
                <button type="button" class="mb mb--ghost" id="xCancelar">Cancelar</button>
                <button type="submit" class="mb mb--primary">Enviar</button>
            </div>
        </form>
    </div>

    @push('styles')
    <style>
        .xaviso{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:14px; padding:11px 14px;
                 border:1px solid rgba(62,125,92,.35); border-radius:11px; background:rgba(62,125,92,.07); color:var(--ok); font-size:13px; }
        .xaviso span{ flex:1 1 200px; color:var(--ink); }
        .xgen{ display:grid; gap:14px; }
        .xfmt{ border:0; margin:0; padding:0; display:grid; gap:8px; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); }
        .xfmt legend{ font-size:12.4px; font-weight:600; color:var(--ink-2); margin-bottom:6px; padding:0; }
        .xfmt__o{ position:relative; cursor:pointer; }
        .xfmt__o input{ position:absolute; opacity:0; }
        .xfmt__o span{ display:block; padding:11px 13px; border:1px solid var(--line); border-radius:10px; background:var(--card);
                       transition:border-color .18s var(--e-soft), box-shadow .18s var(--e-soft); }
        .xfmt__o b{ display:block; font-size:13px; color:var(--ink); }
        .xfmt__o em{ display:block; font-style:normal; font-size:11.8px; color:var(--muted); }
        .xfmt__o input:checked + span{ border-color:var(--a-500); box-shadow:0 0 0 3px rgba(46,110,168,.13); }
        .xfmt__o input:focus-visible + span{ outline:2px solid var(--a-400); outline-offset:2px; }
        .xgen__pie{ display:flex; justify-content:flex-end; }
        .mus-page > * + *{ margin-top:16px; }
        .vmodal{ position:fixed; inset:0; z-index:120; display:grid; place-items:center; padding:20px;
                 background:rgba(7,13,20,.62); backdrop-filter:blur(4px);
                 opacity:0; pointer-events:none; transition:opacity .26s var(--e-soft); }
        .vmodal.is-on{ opacity:1; pointer-events:auto; }
        .vmodal__box{ width:min(460px,100%); padding:22px; background:var(--card); border-radius:15px;
                      box-shadow:0 40px 90px -40px rgba(0,0,0,.6);
                      transform:translateY(14px) scale(.98); transition:transform .3s var(--e-back); }
        .vmodal.is-on .vmodal__box{ transform:none; }
        .vmodal__box h3{ margin:0 0 6px; font-size:16px; font-weight:600; color:var(--ink); }
        .vmodal__box > p{ margin:0 0 12px; font-size:12.4px; color:var(--muted); word-break:break-all; }
        .vmodal__box > label{ font-size:12.4px; font-weight:600; color:var(--ink-2); }
        .vmodal__foot{ display:flex; gap:9px; justify-content:flex-end; margin-top:16px; }
        .xaviso--bad{ border-color:rgba(176,58,46,.35); background:rgba(176,58,46,.06); color:var(--bad); }
        #xModal input[type=text]{ width:100%; height:40px; margin-top:5px; padding:0 11px; border:1px solid var(--line); border-radius:9px; font:inherit; }
    </style>
    @endpush

    @push('scripts')
    <script>
    (function () {
        var modal = document.getElementById('xModal'), form = document.getElementById('xForm');
        var dest = document.getElementById('xDest'), destL = document.getElementById('xDestL');
        var base = @json(url('/reports/exportes'));

        function abrir(id, nombre) {
            form.action = base + '/' + id + '/enviar';
            document.getElementById('xModalN').textContent = nombre;
            modal.classList.add('is-on'); dest.focus();
        }
        document.querySelectorAll('[data-enviar]').forEach(function (b) {
            b.addEventListener('click', function () { abrir(b.dataset.enviar, b.dataset.nombre); });
        });
        document.getElementById('xCancelar').addEventListener('click', function () { modal.classList.remove('is-on'); });
        form.querySelectorAll('input[name=channel]').forEach(function (r) {
            r.addEventListener('change', function () {
                var wa = r.value === 'whatsapp' && r.checked;
                destL.textContent = wa ? 'Número de WhatsApp' : 'Correo del destinatario';
                dest.placeholder = wa ? '300 123 4567' : 'gerencia@negocio.com';
                dest.inputMode = wa ? 'tel' : 'email';
            });
        });
    })();
    </script>
    @endpush
</x-mus.page>
