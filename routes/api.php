<?php

/*
|--------------------------------------------------------------------------
| Rutas API de MEGA UNI STORE
|--------------------------------------------------------------------------
| Superficie stateless consumida por el cliente movil (Flutter).
|
| A diferencia de routes/web.php, este archivo NO pasa por el grupo `web`,
| por lo tanto NO ejecuta EstableceIdioma ni ContextoDeTrabajo. Cualquier
| endpoint de negocio que se agregue aqui debera resolver el tenant de forma
| explicita (token -> negocio_id) antes de tocar los modelos, porque el
| filtro global de multi-tenant depende de ese contexto.
|
| /api/ping es intencionalmente publico y sin dependencias: es la sonda de
| diagnostico de red usada por el emulador Android (10.0.2.2).
*/

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function (Request $request): JsonResponse {
    // Traza de diagnostico: deja constancia en disco de cada sonda recibida.
    // Permite verificar la conectividad emulador -> host sin mirar la consola.
    @file_put_contents(
        storage_path('logs/ping.log'),
        sprintf("[%s] %s %s UA=%s%s", now()->toIso8601String(), $request->ip(),
            $request->fullUrl(), $request->userAgent() ?? '-', PHP_EOL),
        FILE_APPEND
    );

    return response()->json([
        'status'      => 'ok',
        'service'     => 'Mega_Uni_Store API',
        'laravel'     => app()->version(),
        'php'         => PHP_VERSION,
        'environment' => app()->environment(),
        'client_ip'   => $request->ip(),
        'time'        => now()->toIso8601String(),
    ]);
});

Route::get('/health/db', function (): JsonResponse {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();

        return response()->json([
            'status'   => 'ok',
            'database' => \Illuminate\Support\Facades\DB::connection()->getDatabaseName(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage(),
        ], 503);
    }
});
