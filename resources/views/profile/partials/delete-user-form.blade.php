<x-mus.panel title="Eliminar la cuenta" sub="Esta acción no se puede deshacer" class="mprof__danger">
    <p style="font-size:12.8px;line-height:1.65;color:var(--muted)">
        Al eliminar tu cuenta se borran de forma permanente todos sus datos.
        Antes de continuar, descarga cualquier información que quieras conservar.
    </p>

    <form method="POST" action="{{ route('profile.destroy') }}" id="formBorrar"
          data-confirm="¿Eliminar tu cuenta de forma permanente? No hay vuelta atrás."
          style="margin-top:14px">
        @csrf
        @method('delete')

        @php $eb = $errors->userDeletion ?? $errors; @endphp
        <div class="{{ $eb->first('password') ? 'mfld--err' : '' }}" style="max-width:320px">
            <label class="mfld__lab" for="f_del_password">Confirma con tu contraseña</label>
            <div class="mfld__box">
                <input id="f_del_password" name="password" type="password"
                       class="mfld__in" autocomplete="current-password"
                       placeholder="Tu contraseña actual" required>
            </div>
            @if ($eb->first('password'))
                <p class="mfld__err"><x-mus.icon name="alert" :w="12" stroke-width="2.2" />
                    <span>{{ $eb->first('password') }}</span></p>
            @endif
        </div>
    </form>

    <x-slot name="foot">
        <x-mus.btn type="submit" variant="danger" icon="trash" form="formBorrar">
            Eliminar mi cuenta
        </x-mus.btn>
    </x-slot>
</x-mus.panel>
