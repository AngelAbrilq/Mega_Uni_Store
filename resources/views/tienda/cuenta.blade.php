@extends('tienda._base')

@section('titulo', 'Mi cuenta · ' . $empresa->nombre)

@section('tienda')
    <section class="tnd__card" aria-labelledby="tnd-cuenta">
        @if (session('ok'))
            <p class="tnd__ok" role="status">{{ session('ok') }}</p>
        @endif

        <h1 id="tnd-cuenta">Hola, {{ $cliente->first_name }}</h1>
        <p class="tnd__sub">{{ $cliente->email }}</p>

        {{-- El historial de compras se muestra solo con el correo verificado
             (ver RegistroClienteService). Entra junto con el checkout. --}}
        @unless ($cliente->email_verified_at)
            <p class="tnd__sub">Pronto te pediremos confirmar tu correo para ver tu historial de compras.</p>
        @endunless
    </section>
@endsection
