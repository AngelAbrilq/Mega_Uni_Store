<x-mus.page title="Enviar aviso" subtitle="Llega a la campana de cada usuario" icon="info"
            :crumbs="['Notificaciones' => route('notifications.index'), 'Enviar aviso' => null]">
    <form method="POST" action="{{ route('notifications.store') }}" novalidate>
        @csrf
        <x-mus.panel title="Aviso" sub="Úsalo para cambios de horario, inventarios, recordatorios">
            <div class="mf">
                <div class="mf__grid">
                    <x-mus.field name="titulo" label="Título" :required="true" :wide="true" max="120" />
                    <x-mus.textarea name="mensaje" label="Mensaje" :rows="4" :required="true" />
                    <x-mus.select name="nivel" label="Importancia" :required="true" :options="$niveles" value="info" />
                    <x-mus.field name="url" label="Enlace (opcional)" max="255" hint="Una ruta del sistema, ej. /reports o /cash" />
                    @if (auth()->user()->can('sistema.superadmin'))
                        <x-mus.select name="alcance" label="Para quién" :required="true" :wide="true" value="negocio"
                                      :options="['negocio' => 'Solo este negocio', 'todos' => 'Todos los negocios de la plataforma']" />
                    @else
                        <input type="hidden" name="alcance" value="negocio">
                    @endif
                </div>
            </div>
            <x-slot name="foot">
                <x-mus.btn href="{{ route('notifications.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="share">Enviar aviso</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>
</x-mus.page>
