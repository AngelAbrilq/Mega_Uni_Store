<?php

namespace App\Policies\Concerns;

use App\Support\Contexto;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * H-04 · Un documento se VE desde cualquier local del negocio, pero solo se
 * OPERA (anular, recibir, devolver, cerrar, mover) desde su propio local.
 *
 * Así el inventario y la caja se mueven siempre donde pasó el documento, y
 * el rastro dice quién actuó y en qué local (decisión del 2026-10-21).
 */
trait OperaEnSuLocal
{
    protected function enSuLocal(Model $documento): Response
    {
        if ((int) $documento->getAttribute('tienda_id') === (int) Contexto::tiendaId()) {
            return Response::allow();
        }

        $local = $documento->tienda?->nombre ?? 'otro local';

        return Response::deny("Este documento es de «{$local}». Cámbiate a ese local para operarlo.");
    }
}
