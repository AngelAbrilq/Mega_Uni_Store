<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use App\Models\Rol as Role;

class AppServiceProvider extends ServiceProvider
{
    /** Se resuelve una sola vez por petición. */
    private ?bool $hayRoles = null;

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::before(function ($user, string $ability) {
            /**
             * Un cliente de la tienda pública NO es un usuario del panel:
             * vive en otra tabla y entra por otro guard. Si por alguna ruta
             * llegara aquí, no hereda nada — ni siquiera el permiso abierto
             * de la instalación recién migrada.
             */
            if (! method_exists($user, 'hasRole')) {
                return null;
            }

            /**
             * Instalación recién migrada y sin sembrar: todavía no existe
             * ningún rol, así que nadie tendría permisos y el panel entero
             * daría 403. Mientras la tabla esté vacía no se bloquea nada.
             */
            if (! $this->hayRoles()) {
                return true;
            }

            // El superadministrador pasa por encima de cualquier permiso.
            // Devolver null (y no false) deja que Gate siga evaluando el
            // resto de reglas para los demás roles.
            return $user->hasRole('Superadministrador') ? true : null;
        });

        // "hace 2 horas" en vez de "2 hours ago". El middleware de idioma lo
        // vuelve a fijar en cada petición según el usuario; esto es el valor
        // con el que arranca la aplicación (comandos, colas, pruebas).
        Carbon::setLocale(config('app.locale', 'es'));

        $this->directivas();
    }

    /**
     * Atajos de Blade para escribir números.
     *
     * Sin esto, cada vista repite
     * `number_format((float) $v, 0, ',', '.')` — y esa llamada tiene el
     * formato colombiano incrustado. Con la directiva, la vista dice
     * «esto es dinero» y Configuración › Regional decide cómo se ve.
     *
     *   @dinero($venta->total)        →  $1.500
     *   @dineroT($venta->total)       →  igual, con el $ en <i> más pequeño
     *   @cantidad($item->quantity)    →  2,5   (sin ceros de relleno)
     *   @numero($n, 2)                →  1.234,56
     *   @porcentaje($p)               →  18%
     *   @fecha($venta->sold_at)       →  21/08/2026
     *   @fechahora($venta->sold_at)   →  21/08/2026 14:30
     */
    private function directivas(): void
    {
        $f = \App\Support\Formato::class;

        \Illuminate\Support\Facades\Blade::directive(
            'dinero', fn ($e) => "<?php echo e(\\{$f}::moneda({$e})); ?>"
        );

        // Devuelve HTML a propósito (el <i class=\"moneda\">), por eso no
        // pasa por e(). El valor sí se escapa dentro de monedaHtml().
        \Illuminate\Support\Facades\Blade::directive(
            'dineroT', fn ($e) => "<?php echo \\{$f}::monedaHtml({$e}); ?>"
        );

        \Illuminate\Support\Facades\Blade::directive(
            'cantidad', fn ($e) => "<?php echo e(\\{$f}::cantidad({$e})); ?>"
        );

        \Illuminate\Support\Facades\Blade::directive(
            'numero', fn ($e) => "<?php echo e(\\{$f}::numero({$e})); ?>"
        );

        \Illuminate\Support\Facades\Blade::directive(
            'porcentaje', fn ($e) => "<?php echo e(\\{$f}::porcentaje({$e})); ?>"
        );

        \Illuminate\Support\Facades\Blade::directive(
            'fecha', fn ($e) => "<?php echo e(\\{$f}::fecha({$e})); ?>"
        );

        \Illuminate\Support\Facades\Blade::directive(
            'fechahora', fn ($e) => "<?php echo e(\\{$f}::fechaHora({$e})); ?>"
        );
    }

    private function hayRoles(): bool
    {
        if ($this->hayRoles !== null) {
            return $this->hayRoles;
        }

        try {
            $this->hayRoles = Schema::hasTable('roles') && Role::query()->exists();
        } catch (\Throwable $e) {
            $this->hayRoles = false;
        }

        return $this->hayRoles;
    }
}
