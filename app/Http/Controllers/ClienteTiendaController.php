<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalogo\AsociarClienteTiendaRequest;
use App\Models\Customer;
use App\Models\Tienda;
use App\Services\FidelizacionService;
use App\Support\Contexto;
use Illuminate\Http\Request;

/**
 * CL-TDC-001 · El cliente en cada local.
 *
 * Asociar nunca duplica (un cliente, una cuenta por local) y desasociar no
 * borra: deja la cuenta inactiva con sus puntos e historia intactos, para
 * que reactivarla devuelva todo como estaba.
 */
class ClienteTiendaController extends Controller
{
    public function __construct(private FidelizacionService $fidelizacion)
    {
    }

    public function store(AsociarClienteTiendaRequest $request, Customer $customer)
    {
        $tienda = (int) $request->validated('tienda_id');
        $this->fidelizacion->asociar($customer, $tienda);

        return back()->with('ok', $customer->full_name . ' quedó asociado a ' . Tienda::find($tienda)?->nombre . '.');
    }

    public function destroy(Request $request, Customer $customer, int $tienda)
    {
        abort_unless($request->user()->can('clientes.editar'), 403);
        abort_unless(
            in_array($tienda, $request->user()->tiendasEn((int) Contexto::empresaId())->modelKeys(), true),
            403,
        );

        $this->fidelizacion->desasociar($customer, $tienda);

        return back()->with('ok', 'El cliente ya no figura en ' . Tienda::find($tienda)?->nombre . '. Sus puntos se conservan.');
    }
}
