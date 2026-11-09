{{-- TBL-SES-001 · dónde está conectado --}}
<x-mus.panel title="Sesiones abiertas"
             :sub="$sesiones->count() ? $sesiones->count() . ' ' . ($sesiones->count() === 1 ? 'dispositivo' : 'dispositivos') : 'No está conectado'">
    @include('profile.partials.sesiones', ['sesiones' => $sesiones, 'actual' => null, 'propias' => false])
    @if ($sesiones->count() && auth()->id() !== $user->id && auth()->user()->can('usuarios.editar') && ! \App\Support\Jerarquia::esSuperior(auth()->user(), $user))
        <x-slot name="foot">
            <form method="POST" action="{{ route('users.sesiones.cerrar', $user) }}">
                @csrf
                <x-mus.btn type="submit" icon="close">Cerrar todas sus sesiones</x-mus.btn>
            </form>
        </x-slot>
    @endif
</x-mus.panel>
