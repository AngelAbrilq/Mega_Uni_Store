@extends('tienda._base')

@section('titulo', 'Crear cuenta · ' . $empresa->nombre)

@section('tienda')
    <section class="tnd__card" aria-labelledby="tnd-registro">
        <h1 id="tnd-registro">Crear cuenta</h1>
        <p class="tnd__sub">Para comprar en {{ $empresa->nombre }} y seguir tus pedidos.</p>

        <form method="POST" action="{{ route('tienda.registro.enviar', $empresa) }}" novalidate>
            @csrf
            <x-mus.antispam />

            @foreach ([
                ['first_name', 'Nombre', 'text', 'given-name', true],
                ['last_name', 'Apellido', 'text', 'family-name', false],
                ['email', 'Correo electrónico', 'email', 'email', true],
                ['phone', 'Teléfono (opcional)', 'tel', 'tel', false],
            ] as [$campo, $etiqueta, $tipo, $auto, $obligatorio])
                <div class="tnd__campo">
                    <label for="{{ $campo }}">{{ $etiqueta }}</label>
                    <input id="{{ $campo }}" name="{{ $campo }}" type="{{ $tipo }}" value="{{ old($campo) }}"
                           autocomplete="{{ $auto }}" @if ($obligatorio) required @endif
                           @error($campo) aria-invalid="true" aria-describedby="{{ $campo }}-err" @enderror>
                    @error($campo)<p id="{{ $campo }}-err" class="tnd__err" role="alert">{{ $message }}</p>@enderror
                </div>
            @endforeach

            <div class="tnd__campo">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                       aria-describedby="password-ayuda @error('password') password-err @enderror">
                <small id="password-ayuda" style="color:var(--tx-2)">Mínimo 8 caracteres, con letras y números.</small>
                @error('password')<p id="password-err" class="tnd__err" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="tnd__campo">
                <label for="password_confirmation">Repite la contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
            </div>

            <label class="tnd__check">
                <input type="checkbox" name="acepta_privacidad" value="1" @checked(old('acepta_privacidad')) required>
                <span>Acepto la <a class="tnd__link" href="{{ route('sitio.privacidad') }}" target="_blank" rel="noopener">política de tratamiento de datos</a>.</span>
            </label>
            @error('acepta_privacidad')<p class="tnd__err" role="alert">{{ $message }}</p>@enderror

            <button type="submit" class="tnd__btn">Crear cuenta</button>
        </form>

        <p class="tnd__pie">¿Ya tienes cuenta? <a class="tnd__link" href="{{ route('tienda.ingresar', $empresa) }}">Ingresa</a></p>
    </section>
@endsection
