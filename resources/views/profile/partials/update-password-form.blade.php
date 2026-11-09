<x-mus.panel title="Contraseña" sub="Usa una larga y que no repitas en otros sitios">
    <form method="POST" action="{{ route('password.update') }}" id="formClave">
        @csrf
        @method('put')

        <div class="mf">
            {{-- El estilo va en la clase, no en línea: si no, la regla de
                 celular no lo puede pasar a una sola columna. --}}
            <div class="mf__grid mf__grid--3">
                @php $eb = $errors->updatePassword ?? $errors; @endphp

                <div class="{{ $eb->first('current_password') ? 'mfld--err' : '' }}">
                    <label class="mfld__lab" for="f_current_password">Contraseña actual <span class="mfld__req">*</span></label>
                    <div class="mfld__box">
                        <input id="f_current_password" name="current_password" type="password"
                               class="mfld__in" autocomplete="current-password" required>
                    </div>
                    @if ($eb->first('current_password'))
                        <p class="mfld__err"><x-mus.icon name="alert" :w="12" stroke-width="2.2" />
                            <span>{{ $eb->first('current_password') }}</span></p>
                    @endif
                </div>

                <div class="{{ $eb->first('password') ? 'mfld--err' : '' }}">
                    <label class="mfld__lab" for="f_new_password">Contraseña nueva <span class="mfld__req">*</span></label>
                    <div class="mfld__box">
                        <input id="f_new_password" name="password" type="password"
                               class="mfld__in" autocomplete="new-password" required>
                    </div>
                    @if ($eb->first('password'))
                        <p class="mfld__err"><x-mus.icon name="alert" :w="12" stroke-width="2.2" />
                            <span>{{ $eb->first('password') }}</span></p>
                    @else
                        <p class="mfld__hint">Mínimo 8 caracteres.</p>
                    @endif
                </div>

                <div class="{{ $eb->first('password_confirmation') ? 'mfld--err' : '' }}">
                    <label class="mfld__lab" for="f_password_confirmation">Confirmar <span class="mfld__req">*</span></label>
                    <div class="mfld__box">
                        <input id="f_password_confirmation" name="password_confirmation" type="password"
                               class="mfld__in" autocomplete="new-password" required>
                    </div>
                    @if ($eb->first('password_confirmation'))
                        <p class="mfld__err"><x-mus.icon name="alert" :w="12" stroke-width="2.2" />
                            <span>{{ $eb->first('password_confirmation') }}</span></p>
                    @endif
                </div>
            </div>
        </div>
    </form>

    <x-slot name="foot">
        <x-mus.btn type="submit" variant="primary" icon="save" form="formClave">Cambiar contraseña</x-mus.btn>
    </x-slot>
</x-mus.panel>
