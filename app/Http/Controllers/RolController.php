<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Support\Contexto;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles propios del negocio.
 *
 * ── Qué se puede y qué no ──
 *
 * Se pueden crear, editar y borrar los roles PROPIOS. Los siete del
 * sistema se ven —para saber qué hace cada uno y para copiar sus permisos—
 * pero no se tocan: los controladores exigen «Superadministrador» por
 * nombre, y renombrarlo dejaría a todo el mundo por fuera de todo.
 *
 * Un rol creado aquí es de este negocio y de nadie más. La peluquería
 * puede llamarlo «Estilista» aunque otra peluquería del sistema ya tenga
 * uno con ese nombre.
 */
class RolController extends Controller implements HasMiddleware
{
    /**
     * Cómo se agrupan los permisos en el formulario.
     *
     * El orden importa: es el orden en que se leen. Primero lo de vender,
     * que es lo que hace la mayoría todo el día; al final lo de
     * administrar, que lo toca una persona una vez.
     */
    private const GRUPOS = [
        'Vender'        => ['ventas', 'caja', 'clientes'],
        'Catálogo'      => ['productos', 'categorias', 'unidades', 'atributos', 'impuestos'],
        'Inventario'    => ['inventario', 'compras', 'proveedores'],
        'Información'   => ['panel', 'reportes'],
        'Administración' => ['usuarios', 'roles', 'auditoria', 'medios_pago', 'configuracion'],
    ];

    /**
     * En qué orden se leen las acciones.
     *
     * Alfabético no sirve: pondría «Anular» antes que «Ver», y quien marca
     * permisos piensa de menos a más —primero dejar mirar, después dejar
     * tocar, de último dejar deshacer—. Este orden es ese razonamiento.
     */
    private const ORDEN = [
        'ver', 'crear', 'editar', 'eliminar',
        'anular', 'devolver', 'abrir', 'cerrar', 'ajustar', 'recibir', 'gestionar',
    ];

    /** Cómo se lee cada permiso en pantalla. */
    private const ACCIONES = [
        'ver'      => 'Ver',
        'crear'    => 'Crear',
        'editar'   => 'Editar',
        'eliminar' => 'Eliminar',
        'anular'   => 'Anular',
        'devolver' => 'Recibir devoluciones',
        'abrir'    => 'Abrir',
        'cerrar'   => 'Cerrar (arqueo)',
        'ajustar'  => 'Ajustar a mano',
        'recibir'  => 'Recibir mercancía',
        'gestionar' => 'Gestionar',
    ];

    private const MODULOS = [
        'ventas'        => 'Ventas',
        'caja'          => 'Caja',
        'clientes'      => 'Clientes',
        'productos'     => 'Productos',
        'categorias'    => 'Categorías',
        'unidades'      => 'Unidades',
        'atributos'     => 'Atributos',
        'impuestos'     => 'Impuestos',
        'inventario'    => 'Inventario',
        'compras'       => 'Compras',
        'proveedores'   => 'Proveedores',
        'panel'         => 'Panel',
        'reportes'      => 'Reportes',
        'usuarios'      => 'Usuarios',
        'roles'         => 'Roles',
        'auditoria'     => 'Auditoría',
        'medios_pago'   => 'Medios de pago',
        'configuracion' => 'Configuración',
    ];

    public static function middleware(): array
    {
        return [new Middleware('permission:roles.gestionar')];
    }

    /* ═══════════════ Listado ═══════════════ */

    public function index()
    {
        // `orderByRaw`: primero los propios del negocio, que son los que
        // uno viene a administrar; los del sistema quedan abajo, de
        // referencia.
        $roles = Rol::visibles()
            ->withCount(['permissions', 'users'])
            ->orderByRaw('empresa_id IS NULL')
            ->orderBy('name')
            ->get();

        return view('roles.index', compact('roles'));
    }

    /* ═══════════════ Alta ═══════════════ */

    public function create()
    {
        return view('roles.create', $this->listas());
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);

        $rol = Rol::create([
            'name'       => $datos['name'],
            'guard_name' => 'web',
            'empresa_id' => Contexto::empresaId(),
        ]);

        $rol->syncPermissions($datos['permisos'] ?? []);

        $this->olvidarCache();

        return redirect()->route('roles.index')
            ->with('success', 'Rol «' . $rol->name . '» creado.');
    }

    /* ═══════════════ Edición ═══════════════ */

    public function edit(Rol $role)
    {
        $this->soloPropios($role);

        return view('roles.edit', $this->listas() + ['role' => $role]);
    }

    public function update(Request $request, Rol $role)
    {
        $this->soloPropios($role);

        $datos = $this->validar($request, $role);

        $role->name = $datos['name'];
        $role->save();

        $role->syncPermissions($datos['permisos'] ?? []);

        $this->olvidarCache();

        return redirect()->route('roles.index')
            ->with('success', 'Rol «' . $role->name . '» actualizado.');
    }

    /* ═══════════════ Baja ═══════════════ */

    public function destroy(Rol $role)
    {
        $this->soloPropios($role);

        // Borrar un rol que alguien tiene puesto lo deja sin permisos y sin
        // aviso: se entera el lunes, cuando no puede entrar.
        $cuantos = $role->users()->count();

        if ($cuantos > 0) {
            return back()->with(
                'error',
                'El rol «' . $role->name . '» está asignado a ' . $cuantos
                . ' persona(s). Cámbiales el rol antes de borrarlo.'
            );
        }

        $nombre = $role->name;
        $role->delete();

        $this->olvidarCache();

        return redirect()->route('roles.index')
            ->with('success', 'Rol «' . $nombre . '» eliminado.');
    }

    /* ═══════════════ Apoyo ═══════════════ */

    /**
     * Los roles del sistema no se editan desde aquí.
     *
     * Se responde 404 y no 403 a propósito: para este negocio, el rol de
     * otro negocio no existe, y decir «no tienes permiso» sería confirmar
     * que sí existe en algún lado.
     */
    private function soloPropios(Rol $role): void
    {
        abort_unless($role->empresa_id === Contexto::empresaId(), 404);
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Rol $role = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                // Único dentro del negocio. Y además se comprueba contra
                // los del sistema, para que nadie cree un «Cajero» propio
                // que se confunda con el de siempre.
                Rule::unique('roles', 'name')
                    ->where(fn ($q) => $q->where('guard_name', 'web')
                        ->where(fn ($s) => $s->whereNull('empresa_id')
                            ->orWhere('empresa_id', Contexto::empresaId())))
                    ->ignore($role?->id),
            ],
            'permisos'   => ['array'],
            'permisos.*' => ['string', Rule::exists('permissions', 'name')],
        ], [
            'name.required' => 'Ponle un nombre al rol.',
            'name.unique'   => 'Ya existe un rol con ese nombre.',
        ]);
    }

    /**
     * Los permisos, agrupados como se leen.
     *
     * @return array<string, mixed>
     */
    private function listas(): array
    {
        $todos = Permission::orderBy('name')->pluck('name');

        $grupos = [];

        foreach (self::GRUPOS as $titulo => $modulos) {
            foreach ($modulos as $modulo) {
                $delModulo = $todos->filter(
                    fn (string $p) => str_starts_with($p, $modulo . '.')
                );

                if ($delModulo->isEmpty()) {
                    continue;
                }

                $grupos[$titulo][self::MODULOS[$modulo] ?? ucfirst($modulo)] = $delModulo
                    ->sortBy(function (string $p) {
                        $posicion = array_search(explode('.', $p)[1], self::ORDEN, true);

                        // Lo que no esté en la lista va al final, no primero.
                        return $posicion === false ? 99 : $posicion;
                    })
                    ->mapWithKeys(fn (string $p) => [
                        $p => self::ACCIONES[explode('.', $p)[1]] ?? ucfirst(explode('.', $p)[1]),
                    ])
                    ->all();
            }
        }

        return ['grupos' => $grupos];
    }

    /**
     * La caché de permisos se olvida a mano.
     *
     * spatie la guarda para no consultar la base en cada petición. Si no se
     * limpia, el rol cambia en la base pero la gente sigue con los permisos
     * viejos hasta que la caché caduque sola — y nadie entiende por qué.
     */
    private function olvidarCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
