<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalogo\GuardarCategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

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

    public function store(GuardarCategoryRequest $request)
    {
        $data = $request->validated();
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

    public function update(GuardarCategoryRequest $request, Category $category)
    {
        $data = $request->validated();
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
}
