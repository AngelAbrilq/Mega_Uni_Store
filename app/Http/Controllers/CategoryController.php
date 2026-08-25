<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class CategoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:categorias.ver',      only: ['index', 'show']),
            new Middleware('permission:categorias.crear',    only: ['create', 'store']),
            new Middleware('permission:categorias.editar',   only: ['edit', 'update']),
            new Middleware('permission:categorias.eliminar', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $categories = Category::query()
            ->with('parent:id,name')          // evita una consulta por fila
            ->withCount('products')
            ->when($q !== '', fn ($c) => $c->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('categories.index', compact('categories', 'q'));
    }

    public function create()
    {
        $categories = Category::whereNull('parent_id')->orderBy('name')->get();

        return view('categories.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $this->validar($request);
        $data['is_active'] = $request->boolean('is_active');
        $data['image_url'] = $this->guardarImagen($request);

        unset($data['imagen']);

        $categoria = Category::create($data);

        return redirect()->route('categories.index')
            ->with('success', 'Categoría «' . $categoria->name . '» creada.');
    }

    public function show(Category $category)
    {
        $category->load(['parent', 'children']);
        $category->loadCount('products');

        return view('categories.show', compact('category'));
    }

    public function edit(Category $category)
    {
        // Ni ella misma ni sus hijas pueden ser su categoría padre.
        $categories = Category::whereKeyNot($category->id)
            ->where(fn ($c) => $c->whereNull('parent_id')->orWhere('parent_id', '!=', $category->id))
            ->orderBy('name')
            ->get();

        return view('categories.edit', compact('category', 'categories'));
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validar($request, $category);
        $data['is_active'] = $request->boolean('is_active');

        $nueva = $this->guardarImagen($request, $category->image_url);

        if ($nueva !== false) {
            $data['image_url'] = $nueva;
        }

        unset($data['imagen']);

        $category->update($data);

        return redirect()->route('categories.index')
            ->with('success', 'Categoría «' . $category->name . '» actualizada.');
    }

    public function destroy(Category $category)
    {
        // No se borra una categoría que todavía tiene productos colgando.
        if ($category->products()->exists()) {
            return back()->with('error', 'No puedes eliminar «' . $category->name
                . '»: todavía tiene productos asociados.');
        }

        if ($category->children()->exists()) {
            return back()->with('error', 'No puedes eliminar «' . $category->name
                . '»: primero mueve o elimina sus subcategorías.');
        }

        $nombre = $category->name;
        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', 'Categoría «' . $nombre . '» eliminada.');
    }

    /**
     * Igual que en productos: al disco va el archivo, a la columna
     * image_url (VARCHAR) va solamente la ruta "categorias/xxxx.jpg".
     */
    private function guardarImagen(Request $request, ?string $anterior = null): string|null|false
    {
        if ($request->boolean('imagen_eliminar')) {
            $this->borrarArchivo($anterior);

            return null;
        }

        if (! $request->hasFile('imagen')) {
            return $anterior === null ? null : false;
        }

        $ruta = $request->file('imagen')->store('categorias', 'public');

        $this->borrarArchivo($anterior);

        return $ruta;
    }

    private function borrarArchivo(?string $ruta): void
    {
        if ($ruta && ! str_starts_with($ruta, 'http') && Storage::disk('public')->exists($ruta)) {
            Storage::disk('public')->delete($ruta);
        }
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id'   => ['nullable', 'exists:categories,id', Rule::notIn([$category?->id])],
            'imagen'      => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ], [
            'name.required'  => 'La categoría necesita un nombre.',
            'name.unique'    => 'Ya existe una categoría con ese nombre.',
            'parent_id.not_in' => 'Una categoría no puede ser su propia categoría padre.',
            'imagen.image'   => 'El archivo debe ser una imagen.',
            'imagen.mimes'   => 'La imagen debe ser JPG, PNG o WEBP.',
            'imagen.max'     => 'La imagen no puede pesar más de 2 MB.',
        ]);
    }
}
