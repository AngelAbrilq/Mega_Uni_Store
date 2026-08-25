<?php

use App\Http\Controllers\AttributeController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\CashSessionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContextoController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IdiomaController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SistemaController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\TrasladoController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web de MEGA UNI STORE
|--------------------------------------------------------------------------
| Los permisos NO se declaran aquí sino en cada controlador, con el método
| estático middleware(). Así cada acción (ver / crear / editar / eliminar)
| exige su propio permiso, en vez de proteger el módulo entero de golpe.
*/

Route::get('/', function () {
    return view('welcome');
})->name('inicio');

/*
 * La prueba de 2 h 30, sin que nadie tenga que estar disponible.
 *
 * `throttle` de verdad y no por cortesía: es una dirección pública que crea
 * una empresa, un usuario y cerca de cien filas de catálogo por visita. Sin
 * tope, un solo script llena la base en una tarde.
 */
Route::middleware(['guest', 'throttle:6,60'])->group(function () {
    Route::get('/probar', [DemoController::class, 'crear'])->name('demo.crear');
    Route::post('/probar', [DemoController::class, 'guardar'])->name('demo.guardar');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {

    /* ─────────── Cuenta propia ─────────── */
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /* ─────────── Catálogo ─────────── */
    Route::resource('products', ProductController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('units', UnitController::class);
    Route::resource('taxes', TaxController::class);
    Route::resource('attributes', AttributeController::class);

    /* ─────────── Punto de venta ─────────── */
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos', [PosController::class, 'store'])->name('pos.store');

    /* ─────────── Ventas ─────────── */
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::get('/sales/{sale}/recibo', [SaleController::class, 'recibo'])->name('sales.recibo');
    Route::post('/sales/{sale}/anular', [SaleController::class, 'anular'])->name('sales.anular');

    /* ─────────── Devoluciones ─────────── */
    Route::get('/returns', [SaleReturnController::class, 'index'])->name('returns.index');
    Route::get('/sales/{sale}/devolver', [SaleReturnController::class, 'create'])->name('returns.create');
    Route::post('/sales/{sale}/devolver', [SaleReturnController::class, 'store'])->name('returns.store');
    Route::get('/returns/{return}', [SaleReturnController::class, 'show'])->name('returns.show');

    /* ─────────── Búsqueda global (Ctrl + K) ─────────── */
    Route::get('/buscar', SearchController::class)->name('buscar');

    /* ─────────── Caja ─────────── */
    Route::get('/cash', [CashSessionController::class, 'index'])->name('cash.index');
    Route::get('/cash/abrir', [CashSessionController::class, 'create'])->name('cash.create');
    Route::post('/cash', [CashSessionController::class, 'store'])->name('cash.store');
    Route::get('/cash/{cash}', [CashSessionController::class, 'show'])->name('cash.show');
    Route::get('/cash/{cash}/cierre-z', [CashSessionController::class, 'cierreZ'])->name('cash.z');
    Route::post('/cash/{cash}/cerrar', [CashSessionController::class, 'cerrar'])->name('cash.cerrar');

    /* ─────────── Inventario ─────────── */
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/{product}/kardex', [InventoryController::class, 'kardex'])->name('inventory.kardex');
    Route::get('/inventory/{product}/ajustar', [InventoryController::class, 'ajustar'])->name('inventory.ajustar');
    Route::post('/inventory/{product}/ajustar', [InventoryController::class, 'guardarAjuste'])->name('inventory.ajuste.guardar');

    /* ─────────── Compras ─────────── */
    Route::resource('purchases', PurchaseController::class)->except(['destroy']);
    Route::post('/purchases/{purchase}/recibir', [PurchaseController::class, 'recibir'])->name('purchases.recibir');
    Route::post('/purchases/{purchase}/anular', [PurchaseController::class, 'anular'])->name('purchases.anular');

    /* ─────────── Comercial ─────────── */
    Route::resource('customers', CustomerController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('payment_methods', PaymentMethodController::class);

    /* ─────────── Traslados entre locales ─────────── */
    Route::get('/traslados', [TrasladoController::class, 'index'])->name('traslados.index');
    Route::get('/traslados/nuevo', [TrasladoController::class, 'create'])->name('traslados.create');
    Route::post('/traslados', [TrasladoController::class, 'store'])->name('traslados.store');

    /* ─────────── Reportes ─────────── */
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    /* ─────────── Administración ─────────── */
    Route::resource('users', UserController::class);

    /*
     * Roles propios del negocio.
     *
     * Se excluye `show` porque no hay nada que ver que no esté en el
     * formulario de edición: una pantalla más para leer lo mismo.
     */
    Route::resource('roles', RolController::class)->except(['show'])->parameters(['roles' => 'role']);

    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    Route::get('/audit/{audit}', [AuditController::class, 'show'])->name('audit.show');

    /*
     * Qué plan tiene este negocio y cómo va de cupo.
     *
     * Es de solo lectura: cambiar de plan es una conversación comercial,
     * no un botón.
     */
    Route::get('/plan', PlanController::class)->name('plan.index');

    /*
     * ─────────── El panel de Angel ───────────
     *
     * Todos los clientes de todos los negocios. Es el único rincón del
     * sistema que ve por encima del aislamiento, y lo protege un permiso
     * que un solo rol tiene: `sistema.superadmin`.
     */
    Route::prefix('sistema')->name('sistema.')->group(function () {
        Route::get('/', [SistemaController::class, 'index'])->name('index');
        Route::post('/salir', [SistemaController::class, 'salir'])->name('salir');

        Route::prefix('empresas/{empresa}')->group(function () {
            Route::get('/', [SistemaController::class, 'empresa'])->name('empresa');
            Route::post('/entrar', [SistemaController::class, 'entrar'])->name('entrar');
            Route::post('/suscribir', [SistemaController::class, 'suscribir'])->name('suscribir');
            Route::post('/suspender', [SistemaController::class, 'suspender'])->name('suspender');
            Route::post('/reactivar', [SistemaController::class, 'reactivar'])->name('reactivar');
            Route::post('/extender', [SistemaController::class, 'extender'])->name('extender');
        });
    });

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
});

/*
 * Cambio de idioma.
 *
 * Va fuera del grupo con permisos porque no exige ninguno: elegir en qué
 * idioma se le habla a uno no es una operación administrativa. Sí exige
 * sesión iniciada, para poder guardarlo en el perfil del usuario.
 */
Route::post('/idioma', [IdiomaController::class, 'cambiar'])
    ->middleware('auth')
    ->name('idioma.cambiar');

/*
 * Cambio de negocio o de local.
 *
 * Tampoco pide permiso, por la misma razón que el idioma: no es una
 * operación administrativa sino decir dónde estoy parado. Lo que sí hace
 * el controlador —y es lo único que importa aquí— es comprobar que el
 * negocio al que se quiere cambiar sea uno de los suyos.
 *
 * Es POST porque cambia el estado de la sesión, y una dirección que cambia
 * algo con solo abrirla se dispara sola con cualquier precarga.
 */
Route::post('/contexto/cambiar', [ContextoController::class, 'cambiar'])
    ->middleware('auth')
    ->name('contexto.cambiar');

require __DIR__.'/auth.php';
