<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class UnitController extends Controller implements HasMiddleware
{
    /** Familias de medida disponibles. */
    public const TIPOS = ['Conteo', 'Empaque', 'Peso', 'Volumen', 'Longitud', 'Área', 'Tiempo'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:unidades.ver',      only: ['index', 'show']),
            new Middleware('permission:unidades.crear',    only: ['create', 'store']),
            new Middleware('permission:unidades.editar',   only: ['edit', 'update']),
            new Middleware('permission:unidades.eliminar', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $units = Unit::query()
            ->withCount('products')
            ->when($q !== '', fn ($c) => $c->where('name', 'like', "%{$q}%")->orWhere('symbol', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('units.index', compact('units', 'q'));
    }

    public function create()
    {
        return view('units.create', ['tipos' => self::TIPOS]);
    }

    public function store(Request $request)
    {
        $unidad = Unit::create($this->validar($request));

        return redirect()->route('units.index')
            ->with('success', 'Unidad «' . $unidad->name . '» creada.');
    }

    public function show(Unit $unit)
    {
        $unit->loadCount('products');

        return view('units.show', compact('unit'));
    }

    public function edit(Unit $unit)
    {
        return view('units.edit', ['unit' => $unit, 'tipos' => self::TIPOS]);
    }

    public function update(Request $request, Unit $unit)
    {
        $unit->update($this->validar($request, $unit));

        return redirect()->route('units.index')
            ->with('success', 'Unidad «' . $unit->name . '» actualizada.');
    }

    public function destroy(Unit $unit)
    {
        if ($unit->products()->exists()) {
            return back()->with('error', 'No puedes eliminar «' . $unit->name
                . '»: hay productos que se venden en esta unidad.');
        }

        $nombre = $unit->name;
        $unit->delete();

        return redirect()->route('units.index')
            ->with('success', 'Unidad «' . $nombre . '» eliminada.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Unit $unit = null): array
    {
        return $request->validate([
            'name'   => ['required', 'string', 'max:80', Rule::unique('units', 'name')->ignore($unit?->id)],
            'symbol' => ['required', 'string', 'max:10', Rule::unique('units', 'symbol')->ignore($unit?->id)],
            'type'   => ['required', 'string', 'max:50'],
        ], [
            'name.required'   => 'La unidad necesita un nombre (por ejemplo, Kilogramo).',
            'name.unique'     => 'Ya existe una unidad con ese nombre.',
            'symbol.required' => 'Escribe el símbolo corto (kg, L, und…).',
            'symbol.unique'   => 'Ese símbolo ya está en uso.',
            'type.required'   => 'Indica qué tipo de medida es.',
        ]);
    }
}
