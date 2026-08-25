<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class AttributeController extends Controller implements HasMiddleware
{
    /** Cómo se captura el valor del atributo en el formulario del producto. */
    public const TIPOS = [
        'lista'    => 'Lista de opciones (rojo, azul, verde…)',
        'texto'    => 'Texto libre',
        'numero'   => 'Número',
        'booleano' => 'Sí / No',
        'color'    => 'Color',
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:atributos.ver',      only: ['index', 'show']),
            new Middleware('permission:atributos.crear',    only: ['create', 'store']),
            new Middleware('permission:atributos.editar',   only: ['edit', 'update']),
            new Middleware('permission:atributos.eliminar', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $attributes = Attribute::query()
            ->when($q !== '', fn ($c) => $c->where('name', 'like', "%{$q}%"))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('attributes.index', compact('attributes', 'q'));
    }

    public function create()
    {
        return view('attributes.create', ['tipos' => self::TIPOS]);
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $data['is_active'] = $request->boolean('is_active');

        $atributo = Attribute::create($data);

        return redirect()->route('attributes.index')
            ->with('success', 'Atributo «' . $atributo->name . '» creado.');
    }

    public function show(Attribute $attribute)
    {
        return view('attributes.show', compact('attribute'));
    }

    public function edit(Attribute $attribute)
    {
        return view('attributes.edit', ['attribute' => $attribute, 'tipos' => self::TIPOS]);
    }

    public function update(Request $request, Attribute $attribute)
    {
        $data = $this->validar($request, $attribute);
        $data['is_active'] = $request->boolean('is_active');

        $attribute->update($data);

        return redirect()->route('attributes.index')
            ->with('success', 'Atributo «' . $attribute->name . '» actualizado.');
    }

    public function destroy(Attribute $attribute)
    {
        $nombre = $attribute->name;
        $attribute->delete();

        return redirect()->route('attributes.index')
            ->with('success', 'Atributo «' . $nombre . '» eliminado.');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Attribute $atributo = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('attributes', 'name')->ignore($atributo?->id)],
            'type' => ['required', 'string', 'max:50'],
        ], [
            'name.required' => 'El atributo necesita un nombre (Color, Talla…).',
            'name.unique'   => 'Ya existe un atributo con ese nombre.',
            'type.required' => 'Indica cómo se captura el valor.',
        ]);
    }
}
