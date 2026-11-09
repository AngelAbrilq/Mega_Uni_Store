<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalogo\GuardarTaxRequest;
use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TaxController extends Controller implements HasMiddleware
{
    /** percentage = % sobre el precio · fixed = valor fijo por unidad. */
    public const TIPOS = [
        'percentage' => 'Porcentaje sobre el precio',
        'fixed'      => 'Valor fijo por unidad',
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:impuestos.ver',      only: ['index', 'show']),
            new Middleware('permission:impuestos.crear',    only: ['create', 'store']),
            new Middleware('permission:impuestos.editar',   only: ['edit', 'update']),
            new Middleware('permission:impuestos.eliminar', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $taxes = Tax::query()
            ->withCount('products')
            ->when($q !== '', fn ($c) => $c->where('name', 'like', "%{$q}%"))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('taxes.index', compact('taxes', 'q'));
    }

    public function create()
    {
        return view('taxes.create', ['tipos' => self::TIPOS]);
    }

    public function store(GuardarTaxRequest $request)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $impuesto = Tax::create($data);

        return redirect()->route('taxes.index')
            ->with('success', 'Impuesto «' . $impuesto->name . '» creado.');
    }

    public function show(Tax $tax)
    {
        $tax->loadCount('products');

        return view('taxes.show', compact('tax'));
    }

    public function edit(Tax $tax)
    {
        return view('taxes.edit', ['tax' => $tax, 'tipos' => self::TIPOS]);
    }

    public function update(GuardarTaxRequest $request, Tax $tax)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $tax->update($data);

        return redirect()->route('taxes.index')
            ->with('success', 'Impuesto «' . $tax->name . '» actualizado.');
    }

    public function destroy(Tax $tax)
    {
        if ($tax->products()->exists()) {
            return back()->with('error', 'No puedes eliminar «' . $tax->name
                . '»: hay productos que lo aplican.');
        }

        $nombre = $tax->name;
        $tax->delete();

        return redirect()->route('taxes.index')
            ->with('success', 'Impuesto «' . $nombre . '» eliminado.');
    }
}
