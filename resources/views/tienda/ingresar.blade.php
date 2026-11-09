@extends('tienda._base')

@section('titulo', 'Ingresar · ' . $empresa->nombre)

@section('tienda')
    <section class="tnd__card" aria-labelledby="tnd-ingresar">
        <h1 id="tnd-ingresar">Ingresar</h1>
        <p class="tnd__sub">Con tu cuenta de {{ $empresa->nombre }}.</p>

        <form method="POST" action="{{ route('tienda.ingresar.enviar', $empresa) }}" novalidate>
            @csrf

            <div class="tnd__campo">
                <label for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"
                       @error('email') aria-invalid="true" aria-describedby="email-err" @enderror>
                @error('email')<p id="email-err" class="tnd__err" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="tnd__campo">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       @error('password') aria-invalid="true" aria-describedby="password-err" @enderror>
                @error('password')<p id="password-err" class="tnd__err" role="alert">{{ $message }}</p>@enderror
            </div>

            <label class="tnd__check">
                <input type="checkbox" name="remember" value="1"> Recordarme en este equipo
            </label>

            <button type="submit" class="tnd__btn">Ingresar</button>
        </form>

        <p class="tnd__pie">¿No tienes cuenta? <a class="tnd__link" href="{{ route('tienda.registro', $empresa) }}">Créala aquí</a></p>
    </section>
@endsection
