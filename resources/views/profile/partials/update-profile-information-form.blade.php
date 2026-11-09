<x-mus.panel title="Datos personales" sub="Tu nombre y el correo con el que entras">
    <form method="POST" enctype="multipart/form-data" action="{{ route('profile.update') }}" id="formPerfil">
        @csrf
        @method('patch')

        <div class="mf">
            <div class="mf__grid">
                <x-mus.imagen name="foto" label="Tu foto" :actual="auth()->user()->imagen"
                              hint="JPG, PNG o WEBP · máximo 2 MB. Sale en el menú lateral y en la auditoría." />

                <x-mus.field name="name" label="Nombre completo"
                             :value="auth()->user()->name" :required="true" max="255" />

                <x-mus.field name="email" label="Correo electrónico" type="email"
                             :value="auth()->user()->email" :required="true" max="255" />
            </div>
        </div>

        @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
            <div style="margin-top:14px;padding:12px 14px;border-radius:9px;background:#FAF5EC;
                        border:1px solid #EADFC8;font-size:12.6px;color:#7C5C2E">
                Tu correo aún no está verificado.
                <button form="formVerificar" class="mb mb--quiet mb--sm" style="margin-left:6px">
                    Reenviar el correo de verificación
                </button>
            </div>
        @endif
    </form>

    @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail)
        <form method="POST" action="{{ route('verification.send') }}" id="formVerificar" hidden>@csrf</form>
    @endif

    <x-slot name="foot">
        <x-mus.btn type="submit" variant="primary" icon="save" form="formPerfil">Guardar datos</x-mus.btn>
    </x-slot>
</x-mus.panel>
