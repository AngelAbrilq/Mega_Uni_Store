<x-mus.page title="{{ $permiso->name }}" subtitle="{{ $permiso->is_system ? 'Permiso del sistema' : 'Permiso personalizado' }}" icon="key"
            :crumbs="['Todos los negocios' => route('sistema.index'), 'Permisos' => route('sistema.permisos.index'), $permiso->name => null]">
    <form method="POST" action="{{ route('sistema.permisos.update', $permiso) }}" novalidate>
        @csrf @method('PUT')
        <x-mus.panel title="Datos del permiso" sub="Módulo, acción y para qué sirve">
            @include('sistema.permisos.form', ['permiso' => $permiso])
            <x-slot name="foot">
                <x-mus.btn href="{{ route('sistema.permisos.index') }}" icon="back">Cancelar</x-mus.btn>
                <x-mus.btn type="submit" variant="primary" icon="save">Guardar</x-mus.btn>
            </x-slot>
        </x-mus.panel>
    </form>

    <x-mus.panel title="Roles que lo tienen" :sub="$permiso->roles->count() . ' roles · se asigna y se quita desde Roles'" style="margin-top:16px">
        @if ($permiso->roles->count())
            <div class="prm-roles">
                @foreach ($permiso->roles as $r)
                    <span><x-mus.icon name="shield" :w="13" /> {{ $r->name }} <small>{{ $r->empresa_id ? 'propio de un negocio' : 'del sistema' }}</small></span>
                @endforeach
            </div>
        @else
            <p class="sub" style="margin:0">Ningún rol lo tiene todavía.</p>
        @endif
    </x-mus.panel>

    @push('styles')
    <style>
        .prm-roles{ display:flex; flex-wrap:wrap; gap:8px; }
        .prm-roles span{ display:inline-flex; gap:6px; align-items:center; padding:6px 11px; border-radius:999px;
                         border:1px solid var(--line); font-size:13px; font-weight:600; }
        .prm-roles small{ font-weight:500; color:var(--muted); }
    </style>
    @endpush
</x-mus.page>
