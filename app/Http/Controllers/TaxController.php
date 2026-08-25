<?php

namespace App\Http\Controllers;

use App\Models\Tax;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

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

    public function store(Request $request)
    {
        $data = $this->validar($request);
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

    public function update(Request $request, Tax $tax)
    {
        $data = $this->validar($request, $tax);
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

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Tax $tax = null): array
    {
        $esPorcentaje = $request->input('type') !== 'fixed';

        return $request->validate([
            'name'        => ['required', 'string', 'max:80', Rule::unique('taxes', 'name')->ignore($tax?->id)],
            'description' => ['nullable', 'string', 'max:200'],
            'rate'        => ['required', 'numeric', 'min:0', $esPorcentaje ? 'max:100' : 'max:999.99'],
            'type'        => ['required', Rule::in(array_keys(self::TIPOS))],
        ], [
            'name.required' => 'El impuesto necesita un nombre (por ejemplo, IVA general).',
            'name.unique'   => 'Ya existe un impuesto con ese nombre.',
            'rate.required' => 'Indica la tarifa.',
            'rate.max'      => $esPorcentaje
                ? 'Un porcentaje no puede pasar de 100.'
                : 'El valor fijo es demasiado alto.',
            'type.required' => 'Indica si es porcentaje o valor fijo.',
        ]);
    }
}
