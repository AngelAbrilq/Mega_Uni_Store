<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
|
| Para que esto corra solo, el equipo tiene que ejecutar cada minuto:
|
|     php artisan schedule:run
|
| En Windows se configura con el Programador de tareas (está explicado en
| el README). Mientras tanto, los dos comandos se pueden lanzar a mano.
|
*/

// Copia de la base de datos todas las noches, conservando dos semanas.
Schedule::command('mus:respaldo --dias=14')
    ->dailyAt('23:30')
    ->withoutOverlapping();

// Pruebas vencidas hace más de 72 h. La Política de privacidad promete
// este borrado: si se quita esta línea hay que corregir esa página.
Schedule::command('mus:limpiar-demos --hacerlo')
    ->dailyAt('03:15')
    ->withoutOverlapping();

// TBL-SES-001: sesiones vencidas por inactividad quedan marcadas (RN-41).
Schedule::command('mus:expirar-sesiones')
    ->hourly()
    ->withoutOverlapping();

// Listado de reposiciones al abrir la tienda.
Schedule::command('mus:stock-bajo')
    ->weekdays()
    ->at('07:30');
