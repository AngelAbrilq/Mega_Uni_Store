@extends('tienda._base')

@section('titulo', $empresa->nombre)

@section('tienda')
    <section class="tnd__card" style="max-width:780px">
        <h1>Bienvenido a {{ $empresa->nombre }}</h1>
        <p class="tnd__sub">
            @if ($cliente)
                Hola, {{ $cliente->first_name }}. Muy pronto vas a poder comprar aquí en línea.
            @else
                Muy pronto vas a poder ver nuestro catálogo y comprar en línea.
            @endif
        </p>
    </section>
@endsection
