{{--
    Envoltura del portal de clientes (/tienda/{slug}).
    Hereda el layout del sitio público; cada página define @section('tienda').
    $tiendaEmpresa y $tiendaLocal los comparte ContextoDeTienda.
--}}
@extends('sitio.layout')

@push('estilos')
<style>
    .tnd{padding:40px 0 72px;background:var(--claro);min-height:60vh}
    .tnd__barra{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:24px}
    .tnd__marca{font-weight:700;font-size:1.25rem;color:var(--tx);text-decoration:none}
    .tnd__nav{display:flex;gap:12px;align-items:center}
    .tnd__nav a,.tnd__link{color:var(--a-600);font-weight:600}
    .tnd__card{max-width:460px;margin:0 auto;background:#fff;border:1px solid var(--borde);border-radius:var(--radio);padding:32px clamp(20px,5vw,40px);box-shadow:var(--sombra)}
    .tnd__card h1{font-size:1.6rem;margin:0 0 6px}
    .tnd__sub{color:var(--tx-2);margin:0 0 20px}
    .tnd__campo{display:flex;flex-direction:column;gap:6px;margin-bottom:14px}
    .tnd__campo label{font-weight:600;font-size:.92rem}
    .tnd__campo input{border:1px solid var(--borde);border-radius:10px;padding:11px 12px;font:inherit}
    .tnd__campo input:focus-visible{outline:3px solid var(--a-300);outline-offset:1px;border-color:var(--a-500)}
    .tnd__err{color:#B42318;font-size:.85rem;margin:0}
    .tnd__check{display:flex;gap:8px;align-items:flex-start;font-size:.9rem;color:var(--tx-2);margin-bottom:14px}
    .tnd__btn{width:100%;border:0;border-radius:10px;padding:12px 16px;font:inherit;font-weight:700;background:var(--a-500);color:#fff;cursor:pointer;transition:background .2s,transform .2s}
    .tnd__btn:hover{background:var(--a-600);transform:translateY(-1px)}
    .tnd__ok{background:#E8F5EE;border:1px solid #A6D8BB;color:var(--ok);border-radius:10px;padding:10px 14px;margin-bottom:16px}
    .tnd__pie{text-align:center;margin-top:18px;color:var(--tx-2)}
    @media (prefers-reduced-motion:reduce){.tnd__btn{transition:none}.tnd__btn:hover{transform:none}}
</style>
@endpush

@section('contenido')
    <div class="tnd">
        <div class="contenedor">
            <header class="tnd__barra">
                <a class="tnd__marca" href="{{ route('tienda.inicio', $tiendaEmpresa) }}">{{ $tiendaEmpresa->nombre }}</a>
                <nav class="tnd__nav" aria-label="Cuenta">
                    @auth('cliente')
                        <a href="{{ route('tienda.cuenta', $tiendaEmpresa) }}">Mi cuenta</a>
                        <form method="POST" action="{{ route('tienda.salir', $tiendaEmpresa) }}">
                            @csrf
                            <button type="submit" class="tnd__link" style="background:none;border:0;cursor:pointer;font:inherit">Salir</button>
                        </form>
                    @else
                        <a href="{{ route('tienda.ingresar', $tiendaEmpresa) }}">Ingresar</a>
                        <a href="{{ route('tienda.registro', $tiendaEmpresa) }}">Crear cuenta</a>
                    @endauth
                </nav>
            </header>

            @yield('tienda')
        </div>
    </div>
@endsection
