<x-mus.page title="Quién atiende" subtitle="Las personas, sillas o espacios que la agenda puede ocupar" icon="users">
    <x-slot name="actions">
        <x-mus.btn href="{{ route('recursos.create') }}" variant="primary" icon="plus">Agregar</x-mus.btn>
        <x-mus.btn href="{{ route('agenda.index') }}" icon="clock">Ver la agenda</x-mus.btn>
    </x-slot>

    <x-mus.panel :pad="false" :reveal="true">
        @if ($recursos->count())
            <div class="mt-wrap">
                <table class="mt">
                    <thead>
                        <tr>
                            <th>Nombre</th><th>Tipo</th><th>Horario</th>
                            <th>Citas por delante</th><th>Estado</th><th class="act"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recursos as $r)
                            <tr data-row>
                                <td>
                                    <span class="mrec__punto" style="background:{{ $r->color }}"></span>
                                    <b>{{ $r->nombre }}</b>
                                    @if ($r->usuario)
                                        <em class="mrec__cuenta">{{ $r->usuario->name }}</em>
                                    @endif
                                </td>
                                <td>{{ \App\Models\Recurso::TIPOS[$r->tipo] ?? $r->tipo }}</td>
                                <td>
                                    @if ($r->horarios->count())
                                        <span class="mrec__horario">
                                            @foreach ($r->horarios->groupBy('dia_semana') as $dia => $franjas)
                                                <b>{{ mb_substr(\App\Models\Horario::DIAS[$dia] ?? '', 0, 3) }}</b>
                                                {{ $franjas->map(fn ($f) => $f->texto())->implode(' · ') }}<br>
                                            @endforeach
                                        </span>
                                    @else
                                        <em class="mrec__sin">Sin horario: no se le puede agendar</em>
                                    @endif
                                </td>
                                <td>{{ $r->citas_futuras }}</td>
                                <td>@if ($r->activo)
                                        <x-mus.badge tone="ok" :dot="true">Activo</x-mus.badge>
                                    @else
                                        <x-mus.badge tone="off">Inactivo</x-mus.badge>
                                    @endif</td>
                                <td class="act">
                                    <span class="mt__acts">
                                        <x-mus.btn href="{{ route('recursos.edit', $r) }}" variant="ghost"
                                                   :sm="true" class="mb--icon" title="Editar">
                                            <x-mus.icon name="pencil" :w="15" />
                                        </x-mus.btn>
                                        <x-mus.del :action="route('recursos.destroy', $r)" what="a" />
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-mus.empty icon="users" title="Todavía no hay quién atienda"
                         text="Puede ser una persona —un estilista, un técnico— o una cosa: una silla, una cabina, un consultorio. Se manejan igual, porque el problema es el mismo: una sola cita a la vez.">
                <x-slot name="action">
                    <x-mus.btn href="{{ route('recursos.create') }}" variant="primary" icon="plus">Agregar el primero</x-mus.btn>
                </x-slot>
            </x-mus.empty>
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        .mrec__punto{ display:inline-block; width:9px; height:9px; border-radius:99px;
                      margin-right:7px; vertical-align:middle; }
        .mrec__cuenta{ display:block; margin-left:16px; font-style:normal; font-size:11.2px; color:var(--muted); }
        .mrec__horario{ font-size:11.6px; line-height:1.65; color:var(--ink-2); }
        .mrec__horario b{ display:inline-block; width:32px; color:var(--muted); font-weight:600; }
        .mrec__sin{ font-style:normal; font-size:11.6px; color:var(--warn); }
    </style>
    @endpush
</x-mus.page>
