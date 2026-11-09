<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AuditController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:auditoria.ver'),
        ];
    }

    public function index(Request $request)
    {
        $consulta = AuditLog::query()
            ->with('user:id,name')
            ->search($request->query('q'))
            ->latest('id');

        if ($usuario = $request->query('usuario')) {
            $consulta->where('user_id', $usuario);
        }

        if ($evento = $request->query('evento')) {
            $consulta->where('event', $evento);
        }

        if ($modelo = $request->query('modelo')) {
            $consulta->where('auditable_type', 'App\\Models\\' . $modelo);
        }

        if ($desde = $request->date('desde')) {
            $consulta->where('created_at', '>=', $desde->startOfDay());
        }

        if ($hasta = $request->date('hasta')) {
            $consulta->where('created_at', '<=', $hasta->endOfDay());
        }

        return view('audit.index', [
            'registros' => $consulta->paginate(25)->withQueryString(),
            'usuarios'  => User::deLaEmpresa()->orderBy('name')->get(['id', 'name']),
            'eventos'   => [
                'creado'      => 'Creaciones',
                'actualizado' => 'Modificaciones',
                'eliminado'   => 'Eliminaciones',
            ],
            'modelos' => [
                'Product'       => 'Productos',
                'Category'      => 'Categorías',
                'Customer'      => 'Clientes',
                'Supplier'      => 'Proveedores',
                'Unit'          => 'Unidades',
                'Tax'           => 'Impuestos',
                'PaymentMethod' => 'Medios de pago',
                'Attribute'     => 'Atributos',
                'User'          => 'Usuarios',
                'Sale'          => 'Ventas',
                'Purchase'      => 'Compras',
            ],
            'resumen' => [
                'hoy'     => AuditLog::whereDate('created_at', today())->count(),
                'semana'  => AuditLog::where('created_at', '>=', now()->subDays(7))->count(),
                'total'   => AuditLog::count(),
                'borrados'=> AuditLog::where('event', 'eliminado')->count(),
            ],
            'filtros' => [
                'q'       => (string) $request->query('q', ''),
                'usuario' => (string) $request->query('usuario', ''),
                'evento'  => (string) $request->query('evento', ''),
                'modelo'  => (string) $request->query('modelo', ''),
                'desde'   => (string) $request->query('desde', ''),
                'hasta'   => (string) $request->query('hasta', ''),
            ],
        ]);
    }

    public function show(AuditLog $audit)
    {
        $audit->load('user:id,name');

        return view('audit.show', compact('audit'));
    }
}
