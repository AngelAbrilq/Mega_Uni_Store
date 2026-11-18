{{-- Insignia de estado común a nóminas, novedades y prestaciones. --}}
@php
    $tonos = ['borrador' => 'warn', 'pendiente' => 'warn', 'calculada' => 'warn', 'aprobada' => 'info', 'activo' => 'ok',
              'pagada' => 'ok', 'rechazada' => 'bad', 'terminado' => 'off', 'finalizado' => 'off'];
@endphp
<x-mus.badge :tone="$tonos[$estado] ?? 'soft'" :dot="true">{{ $texto ?? ucfirst($estado) }}</x-mus.badge>
