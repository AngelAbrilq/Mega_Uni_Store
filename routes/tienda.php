<?php

use App\Http\Controllers\Tienda\AccesoClienteController;
use App\Http\Controllers\Tienda\PortalController;
use App\Http\Controllers\Tienda\RegistroClienteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portal público de clientes (B2C) — /tienda/{slug}
|--------------------------------------------------------------------------
| `tienda` (ContextoDeTienda) fija el negocio desde el slug ANTES de que
| corra la autenticación: está en la lista de prioridad de bootstrap/app.php.
| Todo lo de aquí usa el guard `cliente`, nunca `web`.
*/

Route::prefix('tienda/{empresa:slug}')
    ->name('tienda.')
    ->middleware('tienda')
    ->group(function () {
        Route::get('/', [PortalController::class, 'inicio'])->name('inicio');

        Route::middleware('guest:cliente')->group(function () {
            Route::get('/ingresar', [AccesoClienteController::class, 'create'])->name('ingresar');
            Route::post('/ingresar', [AccesoClienteController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('ingresar.enviar');

            Route::get('/registro', [RegistroClienteController::class, 'create'])->name('registro');
            Route::post('/registro', [RegistroClienteController::class, 'store'])
                ->middleware(['throttle:6,60', 'antispam'])
                ->name('registro.enviar');
        });

        Route::middleware('auth:cliente')->group(function () {
            Route::get('/cuenta', [PortalController::class, 'cuenta'])->name('cuenta');
            Route::post('/salir', [AccesoClienteController::class, 'destroy'])->name('salir');
        });
    });
