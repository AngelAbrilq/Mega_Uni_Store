<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

/**
 * El plan no da para más.
 *
 * ── Por qué una excepción y no un `return back()` ──
 *
 * Porque el límite se comprueba en el servicio, dentro de la transacción,
 * y desde ahí no se puede «devolver al formulario»: el controlador ya
 * empezó a guardar. Una excepción corta la operación entera y deja la base
 * como estaba, que es exactamente lo que tiene que pasar.
 *
 * Y trae su propio `render()` para que el usuario no vea una pantalla de
 * error del sistema. Ve el mensaje que le importa: qué se acabó, cuánto
 * tiene, y qué hacer al respecto.
 */
class LimiteDelPlan extends Exception
{
    public function __construct(
        public readonly string $recurso,
        public readonly int $limite,
        public readonly string $plan,
        string $mensaje = '',
    ) {
        parent::__construct($mensaje ?: self::redactar($recurso, $limite, $plan));
    }

    /**
     * El mensaje que ve la persona.
     *
     * Dice tres cosas y en este orden: qué se acabó, cuál es el tope, y
     * qué puede hacer. Un «límite alcanzado» a secas deja a alguien mirando
     * la pantalla sin saber si es un error del sistema o algo que le toca
     * resolver a él.
     */
    private static function redactar(string $recurso, int $limite, string $plan): string
    {
        $cosa = match ($recurso) {
            'tiendas'    => 'locales',
            'usuarios'   => 'usuarios',
            'productos'  => 'productos',
            'ventas_mes' => 'ventas este mes',
            default      => $recurso,
        };

        $tope = number_format($limite, 0, ',', '.');

        if ($recurso === 'ventas_mes') {
            return "Tu plan {$plan} incluye {$tope} ventas al mes y ya las registraste todas. "
                 . 'El contador vuelve a cero el primer día del mes que viene. '
                 . 'Si necesitas más desde ya, escríbenos para subirte de plan.';
        }

        return "Tu plan {$plan} incluye hasta {$tope} {$cosa} y ya los tienes todos. "
             . 'Puedes liberar uno que no uses, o escribirnos para subir de plan.';
    }

    /** Lo que ve el navegador: el formulario de vuelta, con el aviso. */
    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'recurso' => $this->recurso,
                'limite'  => $this->limite,
            ], 402);   // 402 Payment Required: es literalmente el caso.
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
