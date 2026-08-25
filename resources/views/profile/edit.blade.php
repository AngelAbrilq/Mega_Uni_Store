<x-mus.page title="Mi perfil" subtitle="Tus datos, tu contraseña y tu cuenta" icon="user">

    <div class="mprof">
        <div class="mprof__col">
            @include('profile.partials.update-profile-information-form')
            @include('profile.partials.update-password-form')
        </div>

        <div class="mprof__col">
            <x-mus.panel title="Resumen" sub="Estado de tu cuenta">
                <div class="mprof__id">
                    <x-mus.avatar :src="auth()->user()->imagen" :letras="auth()->user()->iniciales"
                                  :color="auth()->user()->color_avatar" :size="54" :round="true" />
                    <div>
                        <b>{{ auth()->user()->name }}</b>
                        <em>{{ auth()->user()->email }}</em>
                    </div>
                </div>

                <dl class="mdl" style="margin-top:16px">
                    <div>
                        <dt>Miembro desde</dt>
                        <dd>{{ auth()->user()->created_at?->locale('es')->isoFormat('MMMM YYYY') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Correo verificado</dt>
                        <dd>
                            @if (auth()->user()->email_verified_at)
                                <x-mus.badge tone="ok" :dot="true">Sí</x-mus.badge>
                            @else
                                <x-mus.badge tone="warn" :dot="true">Pendiente</x-mus.badge>
                            @endif
                        </dd>
                    </div>
                    @php $roles = method_exists(auth()->user(), 'getRoleNames') ? auth()->user()->getRoleNames() : collect(); @endphp
                    <div>
                        <dt>Rol</dt>
                        <dd>
                            @if ($roles->isNotEmpty())
                                @foreach ($roles as $rol)
                                    <x-mus.badge tone="info">{{ $rol }}</x-mus.badge>
                                @endforeach
                            @else
                                <span style="color:var(--muted-2)">Sin asignar</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                <x-slot name="foot">
                    <x-mus.btn href="{{ route('dashboard') }}" icon="grid" :block="true">Ir al panel</x-mus.btn>
                </x-slot>
            </x-mus.panel>

            @include('profile.partials.delete-user-form')
        </div>
    </div>

    @push('styles')
        <style>
            .mprof{ display:grid; gap:16px; grid-template-columns:minmax(0,1.5fr) minmax(0,1fr); align-items:start; }
            .mprof__col{ display:grid; gap:16px; }
            .mprof__id{ display:flex; align-items:center; gap:13px; }
            .mprof__av{
                width:48px; height:48px; flex:none; border-radius:12px;
                display:grid; place-items:center; color:#fff; font-size:18px; font-weight:700;
                background:var(--a-600); border:1px solid var(--a-500);
            }
            .mprof__id b{ display:block; font-size:14.5px; font-weight:700; color:var(--ink); }
            .mprof__id em{ display:block; font-style:normal; font-size:12.2px; color:var(--muted-2); margin-top:2px; }
            .mprof__danger{ border-color:#E9D7D7; }
            .mprof__danger .mp__head h3{ color:var(--bad); }
            @media (max-width:960px){ .mprof{ grid-template-columns:minmax(0,1fr); } }
        </style>
    @endpush
</x-mus.page>
