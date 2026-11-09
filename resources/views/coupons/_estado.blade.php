@php
    $tonos  = ['vigente' => 'ok', 'programado' => 'info', 'vencido' => 'off', 'agotado' => 'warn', 'inactivo' => 'off'];
    $textos = ['vigente' => 'Vigente', 'programado' => 'Programado', 'vencido' => 'Vencido', 'agotado' => 'Agotado', 'inactivo' => 'Inactivo'];
@endphp
<x-mus.badge :tone="$tonos[$cupon->estado]" :dot="true">{{ $textos[$cupon->estado] }}</x-mus.badge>
