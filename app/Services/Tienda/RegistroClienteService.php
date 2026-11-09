<?php

namespace App\Services\Tienda;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crea —o reclama— la cuenta de un cliente en la tienda de un negocio.
 *
 * `customers` es la MISMA tabla del punto de venta (ver migración
 * 2026_08_21_700003). Con el correo único por empresa, hay tres casos:
 *
 *   1. No existe            → se crea.
 *   2. Existe SIN contraseña → es un cliente del mostrador: se le pone la
 *                              contraseña y conserva su historia.
 *   3. Existe borrado        → se restaura: es la misma persona volviendo.
 *   (Existe CON contraseña  → error: ya tiene cuenta, que ingrese.)
 *
 * ⚠ El caso 2 deja que quien conoce el correo de un cliente del mostrador
 * le ponga contraseña. Por eso la cuenta queda con `email_verified_at` en
 * nulo, y nada sensible (historial de compras, direcciones) se debe mostrar
 * hasta verificar el correo. Esa verificación entra con el checkout.
 */
class RegistroClienteService
{
    /**
     * @param  array{first_name:string, last_name:?string, email:string, phone:?string, password:string}  $datos
     *
     * @throws ValidationException
     */
    public function registrar(array $datos): Customer
    {
        return DB::transaction(function () use ($datos) {
            // El filtro por empresa ya está puesto (ContextoDeTienda): esta
            // búsqueda solo ve los clientes de ESTE negocio.
            $cliente = Customer::withTrashed()
                ->where('email', $datos['email'])
                ->lockForUpdate()
                ->first();

            if ($cliente && $cliente->tiene_cuenta && ! $cliente->trashed()) {
                throw ValidationException::withMessages([
                    'email' => 'Ya hay una cuenta con ese correo. Ingresa con tu contraseña.',
                ]);
            }

            if ($cliente) {
                return $this->reclamar($cliente, $datos);
            }

            $nuevo = new Customer([
                'first_name' => $datos['first_name'],
                'last_name'  => $datos['last_name'],
                'email'      => $datos['email'],
                'phone'      => $datos['phone'],
            ]);

            // `password` no es fillable a propósito: se asigna explícito.
            $nuevo->password = $datos['password'];
            $nuevo->save();

            return $nuevo;
        });
    }

    /**
     * Le pone cuenta a un registro que ya existía.
     *
     * Los datos que el cajero ya tenía (documento, dirección) no se pisan;
     * solo se completan los vacíos.
     */
    private function reclamar(Customer $cliente, array $datos): Customer
    {
        if ($cliente->trashed()) {
            $cliente->restore();
        }

        $cliente->first_name = $cliente->first_name ?: $datos['first_name'];
        $cliente->last_name  = $cliente->last_name ?: $datos['last_name'];
        $cliente->phone      = $cliente->phone ?: $datos['phone'];
        $cliente->password   = $datos['password'];
        $cliente->email_verified_at = null;
        $cliente->save();

        return $cliente;
    }
}
