<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /**
         * Alias de spatie/laravel-permission.
         * Sin esto, escribir middleware('permission:productos.ver') en un
         * controlador lanza "Target class [permission] does not exist".
         */
        $middleware->alias([
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        /**
         * Idioma y zona horaria de cada petición.
         *
         * Va en el grupo `web` y no como alias porque tiene que correr en
         * TODAS las pantallas —incluida la de ingreso— sin que haya que
         * acordarse de ponerlo ruta por ruta.
         *
         * Se agrega DESPUÉS de la sesión (appendToGroup lo pone al final de
         * la fila): el middleware lee `session('idioma')`, y si corriera
         * antes de que la sesión esté iniciada esa lectura siempre daría
         * vacío y el selector de idioma no haría nada.
         */
        $middleware->appendToGroup('web', \App\Http\Middleware\EstableceIdioma::class);

        /**
         * En qué negocio y en qué local trabaja esta petición.
         *
         * Va en el grupo `web` y no como alias por la misma razón que el
         * idioma, pero con más peso: si se pusiera ruta por ruta, la ruta
         * que se olvidara correría sin contexto —y sin contexto el filtro
         * de los modelos no filtra—. El día que alguien agregue una
         * pantalla nueva y no se acuerde, esa pantalla mostraría los datos
         * de todos los clientes.
         *
         * Aquí no hay nada que acordarse: toda ruta web pasa por él.
         *
         * Va al final de la fila, después de la sesión, porque necesita
         * saber quién entró y qué escogió en el selector, y las dos cosas
         * viven en la sesión.
         */
        $middleware->appendToGroup('web', \App\Http\Middleware\ContextoDeTrabajo::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
