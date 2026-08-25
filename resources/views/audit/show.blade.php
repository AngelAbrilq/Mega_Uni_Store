@php
    $tonos = ['creado' => 'ok', 'actualizado' => 'info', 'eliminado' => 'bad', 'restaurado' => 'warn'];

    $mostrar = function ($v) {
        if ($v === null || $v === '') return '—';
        if (is_bool($v)) return $v ? 'Sí' : 'No';
        if ($v === 1 || $v === '1') return '1';
        if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE);
        return (string) $v;
    };
@endphp

<x-mus.page title="{{ $audit->evento_label }} {{ $audit->modelo }}"
            subtitle="{{ $audit->auditable_label }} · {{ $audit->created_at?->locale('es')->isoFormat('D [de] MMMM [de] YYYY, HH:mm:ss') }}"
            icon="key"
            :crumbs="['Auditoría' => route('audit.index'), 'Detalle' => null]">

    <div class="vgrid">
        <x-mus.panel title="Qué cambió" :pad="false"
                     sub="{{ count($audit->cambios) }} campo(s) distinto(s)">
            @if (count($audit->cambios))
                <div class="mt-wrap">
                    <table class="mt">
                        <thead>
                            <tr><th>Campo</th><th>Antes</th><th>Después</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($audit->cambios as $c)
                                <tr>
                                    <td><b>{{ $c['campo'] }}</b></td>
                                    <td><span class="adif adif--antes">{{ $mostrar($c['antes']) }}</span></td>
                                    <td><span class="adif adif--despues">{{ $mostrar($c['despues']) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif ($audit->event === 'creado')
                <div class="mt-wrap">
                    <table class="mt">
                        <thead><tr><th>Campo</th><th>Valor inicial</th></tr></thead>
                        <tbody>
                            @foreach (($audit->new_values ?? []) as $campo => $valor)
                                <tr>
                                    <td><b>{{ $campo }}</b></td>
                                    <td><span class="adif adif--despues">{{ $mostrar($valor) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif ($audit->event === 'eliminado')
                <div class="mt-wrap">
                    <table class="mt">
                        <thead><tr><th>Campo</th><th>Valor al eliminarse</th></tr></thead>
                        <tbody>
                            @foreach (($audit->old_values ?? []) as $campo => $valor)
                                <tr>
                                    <td><b>{{ $campo }}</b></td>
                                    <td><span class="adif adif--antes">{{ $mostrar($valor) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-mus.empty icon="info" title="Sin diferencias registradas" />
            @endif
        </x-mus.panel>

        <x-mus.panel title="Rastro">
            <dl class="mdl">
                <div>
                    <dt>Usuario</dt>
                    <dd>{{ $audit->user_name ?? $audit->user->name ?? 'Sistema' }}</dd>
                </div>
                <div>
                    <dt>Acción</dt>
                    <dd>
                        <x-mus.badge :tone="$tonos[$audit->event] ?? 'soft'" :dot="true">
                            {{ $audit->evento_label }}
                        </x-mus.badge>
                    </dd>
                </div>
                <div><dt>Módulo</dt><dd>{{ $audit->modelo }}</dd></div>
                <div><dt>Registro</dt><dd>#{{ $audit->auditable_id }}</dd></div>
                <div><dt>Fecha</dt><dd>{{ $audit->created_at?->format('d/m/Y H:i:s') }}</dd></div>
                <div><dt>Dirección IP</dt><dd>{{ $audit->ip ?: '—' }}</dd></div>
                <div>
                    <dt>Dirección</dt>
                    <dd style="word-break:break-all;font-size:11.6px">{{ $audit->url ?: '—' }}</dd>
                </div>
                <div>
                    <dt>Navegador</dt>
                    <dd style="word-break:break-all;font-size:11.6px">
                        {{ \Illuminate\Support\Str::limit($audit->user_agent, 90) ?: '—' }}
                    </dd>
                </div>
            </dl>

            <x-slot name="foot">
                <x-mus.btn href="{{ route('audit.index') }}" icon="back" :block="true">
                    Volver a la auditoría
                </x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </div>

    @push('styles')
    <style>
        .vgrid{ display:grid; gap:16px; grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);
                align-items:start; }
        @media (max-width:980px){ .vgrid{ grid-template-columns:minmax(0,1fr); } }

        .adif{ display:inline-block; padding:3px 8px; border-radius:6px; font-size:12.4px;
               font-family:ui-monospace,SFMono-Regular,Menlo,monospace; word-break:break-word; }
        .adif--antes{ background:rgba(150,80,79,.09); color:#7E4443;
                      text-decoration:line-through; text-decoration-color:rgba(150,80,79,.45); }
        .adif--despues{ background:rgba(62,125,92,.1); color:#2F6047; }
    </style>
    @endpush
</x-mus.page>
