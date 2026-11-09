<?php

use App\Http\Controllers\Nomina;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CashSessionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContextoController;
use App\Http\Controllers\ClienteTiendaController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\WorkScheduleController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IdiomaController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PermisoController;
use App\Http\Controllers\PlanCatalogoController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SmartInventoryController;
use App\Http\Controllers\RecursoController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\MonitorVentasController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SalePaymentController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SaleVoidRequestController;
use App\Http\Controllers\SesionController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SistemaController;
use App\Http\Controllers\SitioController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\TiendaController;
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

/*
 * Sitio público: landing, páginas legales y archivos para buscadores.
 * Todo es GET, sin sesión de usuario y sin datos de inquilinos.
 */
Route::get('/', [SitioController::class, 'inicio'])->name('inicio');
Route::get('/aviso-legal', [SitioController::class, 'aviso'])->name('sitio.aviso');
Route::get('/privacidad', [SitioController::class, 'privacidad'])->name('sitio.privacidad');
Route::get('/cookies', [SitioController::class, 'cookies'])->name('sitio.cookies');
Route::get('/sitemap.xml', [SitioController::class, 'sitemap'])->name('sitio.sitemap');
Route::get('/robots.txt', [SitioController::class, 'robots'])->name('sitio.robots');
Route::get('/site.webmanifest', [SitioController::class, 'manifest'])->name('sitio.manifest');

// NR-NOM-008 E3: el QR del desprendible lleva aquí (público, sin datos más allá de lo impreso).
Route::get('/verificar/nomina/{codigo}', Nomina\VerificacionNominaController::class)
    ->middleware('throttle:30,1')->name('nomina.verificar');

// RP-EXP-001: enlace firmado (7 días) que se comparte por WhatsApp.
Route::get('/exportes/{export}/archivo', [ReportExportController::class, 'publico'])
    ->middleware(['signed', 'throttle:30,1'])
    ->name('exportes.publico');

/*
 * La prueba de 2 h 30, sin que nadie tenga que estar disponible.
 *
 * `throttle` de verdad y no por cortesía: es una dirección pública que crea
 * una empresa, un usuario y cerca de cien filas de catálogo por visita. Sin
 * tope, un solo script llena la base en una tarde.
 */
Route::middleware(['guest', 'throttle:6,60'])->group(function () {
    Route::get('/probar', [DemoController::class, 'crear'])->name('demo.crear');
    Route::post('/probar', [DemoController::class, 'guardar'])
        ->middleware('antispam')
        ->name('demo.guardar');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {

    /* ─────────── Cuenta propia ─────────── */
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // TBL-SES-001 · sesiones activas
    Route::delete('/sesiones/{sesion}', [SesionController::class, 'destroy'])->whereNumber('sesion')->name('sesiones.destroy');
    Route::post('/sesiones/cerrar-otras', [SesionController::class, 'cerrarOtras'])->name('sesiones.cerrar_otras');
    Route::post('/users/{user}/sesiones/cerrar', [SesionController::class, 'cerrarDeUsuario'])->name('users.sesiones.cerrar');

    /* ─────────── Catálogo ─────────── */
    Route::resource('products', ProductController::class);
    Route::resource('categories', CategoryController::class);
    Route::resource('units', UnitController::class);
    Route::resource('taxes', TaxController::class);
    Route::resource('attributes', AttributeController::class);

    /* ─────────── Punto de venta ─────────── */
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos', [PosController::class, 'store'])->name('pos.store');

    /*
     * Pulso al cajón monedero. El throttle no es paranoia: el guion del
     * mostrador la dispara en cada clic de «Cobrar» y un doble clic nervioso
     * mandaría dos pulsos seguidos al solenoide.
     */
    Route::post('/pos/cajon', [PosController::class, 'abrirCajon'])
        ->middleware('throttle:30,1')
        ->name('pos.cajon');

    /*
     * Diagnóstico del cajón. GET porque no cambia nada: solo pregunta si el
     * destino responde. El sondeo va cacheado 10 s en el servicio, así que
     * el indicador del mostrador puede refrescarse sin castigar la cola de
     * impresión de Windows.
     */
    // VD-CUP-001 · vista previa del cupón (no gasta usos).
    Route::get('/pos/cupon', [PosController::class, 'cupon'])
        ->middleware('throttle:30,1')
        ->name('pos.cupon');

    Route::get('/pos/cajon/estado', [PosController::class, 'estadoCajon'])
        ->middleware('throttle:60,1')
        ->name('pos.cajon.estado');

    /* ─────────── Ventas ─────────── */
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    // SV-VTA-001 · ventas del día en vivo (antes de /sales/{sale})
    Route::get('/sales/monitor', [MonitorVentasController::class, 'index'])->name('sales.monitor');
    Route::get('/sales/monitor/datos', [MonitorVentasController::class, 'datos'])->name('sales.monitor.datos');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::get('/sales/{sale}/recibo', [SaleController::class, 'recibo'])->name('sales.recibo');
    Route::get('/sales/{sale}/recibo/escpos', [SaleController::class, 'reciboEscPos'])->name('sales.recibo.escpos');
    Route::post('/sales/{sale}/anular', [SaleController::class, 'anular'])->name('sales.anular');
    // VD-VTA-002 · anular con aprobación (RN-43)
    Route::post('/sales/{sale}/solicitar-anulacion', [SaleVoidRequestController::class, 'store'])->name('void_requests.store');
    Route::get('/anulaciones', [SaleVoidRequestController::class, 'index'])->name('void_requests.index');
    Route::post('/anulaciones/{solicitud}/aprobar', [SaleVoidRequestController::class, 'aprobar'])->whereNumber('solicitud')->name('void_requests.aprobar');
    Route::post('/anulaciones/{solicitud}/rechazar', [SaleVoidRequestController::class, 'rechazar'])->whereNumber('solicitud')->name('void_requests.rechazar');

    /* ─────────── Pagos por verificar (TBL-PAG-001, RN-32) ─────────── */
    Route::get('/payments', [SalePaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments/{payment}/aprobar', [SalePaymentController::class, 'aprobar'])->name('payments.aprobar');
    Route::post('/payments/{payment}/rechazar', [SalePaymentController::class, 'rechazar'])->name('payments.rechazar');

    /* ─────────── Devoluciones ─────────── */
    Route::get('/returns', [SaleReturnController::class, 'index'])->name('returns.index');
    Route::get('/sales/{sale}/devolver', [SaleReturnController::class, 'create'])->name('returns.create');
    Route::post('/sales/{sale}/devolver', [SaleReturnController::class, 'store'])->name('returns.store');
    Route::get('/returns/{return}', [SaleReturnController::class, 'show'])->name('returns.show');
    Route::post('/returns/{return}/aprobar', [SaleReturnController::class, 'aprobar'])->name('returns.aprobar');
    Route::post('/returns/{return}/rechazar', [SaleReturnController::class, 'rechazar'])->name('returns.rechazar');

    /* ─────────── Búsqueda global (Ctrl + K) ─────────── */
    Route::get('/buscar', SearchController::class)->name('buscar');

    /* ─────────── Caja ─────────── */
    Route::get('/cash', [CashSessionController::class, 'index'])->name('cash.index');
    Route::get('/cash/abrir', [CashSessionController::class, 'create'])->name('cash.create');
    Route::post('/cash', [CashSessionController::class, 'store'])->name('cash.store');
    Route::get('/cash/{cash}', [CashSessionController::class, 'show'])->name('cash.show');
    Route::get('/cash/{cash}/cierre-z', [CashSessionController::class, 'cierreZ'])->name('cash.z');
    Route::post('/cash/{cash}/cerrar', [CashSessionController::class, 'cerrar'])->name('cash.cerrar');
    Route::post('/cash/{cash}/movimientos', [CashSessionController::class, 'movimiento'])->name('cash.movimientos.store');

    /* Cajas registradoras físicas del local (AT-CAJ-001). */
    Route::resource('cash_registers', CashRegisterController::class)->except(['show']);

    /* ─────────── Inventario ─────────── */
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/{product}/kardex', [InventoryController::class, 'kardex'])->name('inventory.kardex');
    Route::get('/inventory/{product}/ajustar', [InventoryController::class, 'ajustar'])->name('inventory.ajustar');
    Route::post('/inventory/{product}/ajustar', [InventoryController::class, 'guardarAjuste'])->name('inventory.ajuste.guardar');

    /* ─────────── Compras ─────────── */
    Route::resource('purchases', PurchaseController::class)->except(['destroy']);
    Route::post('/purchases/{purchase}/recibir', [PurchaseController::class, 'recibir'])->name('purchases.recibir');
    Route::post('/purchases/{purchase}/anular', [PurchaseController::class, 'anular'])->name('purchases.anular');

    /* ─────────── Smart Inventory Entry (IA) ─────────── */
    Route::prefix('smart-inventory')->name('smart-inventory.')->middleware('permission:compras.crear')->group(function () {
        Route::get('/', [SmartInventoryController::class, 'create'])->name('create');
        // Cada lectura cuesta dinero en la API de IA: límite por usuario.
        Route::post('/analizar', [SmartInventoryController::class, 'analyze'])->middleware('throttle:10,1')->name('analyze');
        Route::post('/confirmar', [SmartInventoryController::class, 'confirm'])->middleware('throttle:20,1')->name('confirm');
        Route::post('/productos', [SmartInventoryController::class, 'storeProduct'])
            ->middleware(['permission:productos.crear', 'throttle:30,1'])->name('products.store');
    });

    /* ─────────── Comercial ─────────── */
    // CL-TDC-001 · el cliente en cada local
    Route::post('customers/{customer}/locales', [ClienteTiendaController::class, 'store'])->name('customers.locales.store');
    Route::delete('customers/{customer}/locales/{tienda}', [ClienteTiendaController::class, 'destroy'])
        ->whereNumber('tienda')->name('customers.locales.destroy');
    Route::resource('customers', CustomerController::class);

    // RRHH operativo (RN-44): empleados, áreas, cargos y horarios del local
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->whereNumber('employee')->withTrashed()->name('employees.show');
    Route::resource('employees', EmployeeController::class)->except('show');
    Route::post('employees/{employee}/asignar', [EmployeeController::class, 'asignar'])->name('employees.asignar');
    Route::delete('employees/{employee}/areas/{department}', [EmployeeController::class, 'quitarArea'])->name('employees.areas.quitar');
    Route::resource('departments', DepartmentController::class);
    Route::resource('positions', PositionController::class)->except('show');
    Route::resource('work_schedules', WorkScheduleController::class)->parameters(['work_schedules' => 'work_schedule']);
    Route::post('work_schedules/{work_schedule}/turnos', [WorkScheduleController::class, 'agregarTurno'])->name('work_schedules.turnos.store');
    Route::delete('work_schedules/{work_schedule}/turnos/{shift}', [WorkScheduleController::class, 'quitarTurno'])->name('work_schedules.turnos.destroy');

    // Nómina (RN-45 · NR-NOM-001…010)
    Route::prefix('nomina')->group(function () {
        Route::get('contratos', [Nomina\ContratoController::class, 'index'])->name('contracts.index');
        Route::post('empleados/{employee}/contratos', [Nomina\ContratoController::class, 'store'])->name('contracts.store');
        Route::post('contratos/{contrato}/renovar', [Nomina\ContratoController::class, 'renovar'])->name('contracts.renovar');
        Route::post('contratos/{contrato}/terminar', [Nomina\ContratoController::class, 'terminar'])->name('contracts.terminar');

        Route::resource('conceptos', Nomina\ConceptoNominaController::class)->except(['show', 'destroy'])
            ->parameters(['conceptos' => 'concepto'])->names('payroll_concepts');
        Route::patch('conceptos/{concepto}/estado', [Nomina\ConceptoNominaController::class, 'estado'])->name('payroll_concepts.estado');

        Route::get('horas-extra', [Nomina\HorasExtraController::class, 'index'])->name('overtime.index');
        Route::post('horas-extra', [Nomina\HorasExtraController::class, 'store'])->name('overtime.store');
        Route::get('horas-extra/excel', [Nomina\HorasExtraController::class, 'excel'])->name('overtime.excel');
        Route::post('horas-extra/{horas}/aprobar', [Nomina\HorasExtraController::class, 'aprobar'])->name('overtime.aprobar');
        Route::post('horas-extra/{horas}/rechazar', [Nomina\HorasExtraController::class, 'rechazar'])->name('overtime.rechazar');

        Route::get('ausencias', [Nomina\AusenciaController::class, 'index'])->name('leaves.index');
        Route::post('ausencias', [Nomina\AusenciaController::class, 'store'])->name('leaves.store');
        Route::post('ausencias/{ausencia}/aprobar', [Nomina\AusenciaController::class, 'aprobar'])->name('leaves.aprobar');
        Route::post('ausencias/{ausencia}/rechazar', [Nomina\AusenciaController::class, 'rechazar'])->name('leaves.rechazar');
        Route::get('ausencias/{ausencia}/soporte', [Nomina\AusenciaController::class, 'soporte'])->name('leaves.soporte');

        Route::get('prestaciones', [Nomina\PrestacionController::class, 'index'])->name('benefits.index');
        Route::post('prestaciones', [Nomina\PrestacionController::class, 'store'])->name('benefits.store');
        Route::post('prestaciones/final', [Nomina\PrestacionController::class, 'final'])->name('benefits.final');
        Route::post('prestaciones/pagar', [Nomina\PrestacionController::class, 'pagar'])->name('benefits.pagar');
        Route::delete('prestaciones/{prestacion}', [Nomina\PrestacionController::class, 'destroy'])->name('benefits.destroy');

        Route::get('consolidado', [Nomina\ConsolidadoNominaController::class, 'index'])->name('payroll_report.index');
        Route::get('consolidado/excel', [Nomina\ConsolidadoNominaController::class, 'excel'])->name('payroll_report.excel');

        Route::get('parametros', [Nomina\NominaController::class, 'parametros'])->name('payrolls.parametros');
        Route::put('parametros', [Nomina\NominaController::class, 'guardarParametros'])->name('payrolls.parametros.guardar');

        Route::get('/', [Nomina\NominaController::class, 'index'])->name('payrolls.index');
        Route::post('/', [Nomina\NominaController::class, 'store'])->name('payrolls.store');
        Route::get('{payroll}', [Nomina\NominaController::class, 'show'])->whereNumber('payroll')->name('payrolls.show');
        Route::delete('{payroll}', [Nomina\NominaController::class, 'destroy'])->whereNumber('payroll')->name('payrolls.destroy');
        Route::post('{payroll}/recalcular', [Nomina\NominaController::class, 'recalcular'])->name('payrolls.recalcular');
        Route::post('{payroll}/aprobar', [Nomina\NominaController::class, 'aprobar'])->name('payrolls.aprobar');
        Route::post('{payroll}/pagar', [Nomina\NominaController::class, 'pagar'])->name('payrolls.pagar');
        Route::post('{payroll}/ajuste', [Nomina\NominaController::class, 'ajuste'])->name('payrolls.ajuste');
        Route::post('{payroll}/conceptos', [Nomina\NominaController::class, 'agregarConcepto'])->name('payrolls.conceptos.store');
        Route::delete('{payroll}/conceptos/{item}', [Nomina\NominaController::class, 'quitarConcepto'])->name('payrolls.conceptos.destroy');
        Route::get('{payroll}/pila', [Nomina\NominaController::class, 'pila'])->name('payrolls.pila');
        Route::get('{payroll}/desprendibles', [Nomina\NominaController::class, 'desprendibles'])->name('payrolls.desprendibles');
        Route::post('{payroll}/desprendibles/enviar', [Nomina\NominaController::class, 'enviar'])->name('payrolls.enviar');
        Route::get('{payroll}/desprendibles/{fila}', [Nomina\NominaController::class, 'desprendible'])->name('payrolls.desprendible');
    });

    Route::resource('suppliers', SupplierController::class);
    Route::resource('payment_methods', PaymentMethodController::class);
    Route::resource('coupons', CouponController::class); // AT-CUP-001

    /* ─────────── Agenda ─────────── */
    Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');
    Route::post('/agenda', [AgendaController::class, 'store'])->name('agenda.store');
    Route::get('/agenda/citas/{cita}', [AgendaController::class, 'show'])->name('agenda.cita');
    Route::post('/agenda/citas/{cita}/mover', [AgendaController::class, 'mover'])->name('agenda.mover');
    Route::post('/agenda/citas/{cita}/estado', [AgendaController::class, 'estado'])->name('agenda.estado');
    Route::post('/agenda/citas/{cita}/cobrar', [AgendaController::class, 'cobrar'])->name('agenda.cobrar');

    /* Quién atiende, sus horarios y sus bloqueos. */
    Route::resource('recursos', RecursoController::class)->except(['show']);
    Route::post('/recursos/{recurso}/bloquear', [RecursoController::class, 'bloquear'])->name('recursos.bloquear');
    Route::delete('/recursos/{recurso}/bloqueos/{bloqueo}', [RecursoController::class, 'desbloquear'])
        ->name('recursos.desbloquear');

    /* ─────────── Traslados entre locales ─────────── */
    Route::get('/traslados', [TrasladoController::class, 'index'])->name('traslados.index');
    Route::get('/traslados/nuevo', [TrasladoController::class, 'create'])->name('traslados.create');
    Route::post('/traslados', [TrasladoController::class, 'store'])->name('traslados.store');

    /* ─────────── Los locales del negocio ─────────── */

    /*
     * `principal` va aparte del `resource` y es POST.
     *
     * Nombrar principal a otra sede mueve de dónde heredan el NIT y la
     * razón social todos los documentos del negocio. Es una decisión, no
     * un campo del formulario, y una dirección que la ejecute con solo
     * abrirla se dispara sola con cualquier precarga del navegador.
     */
    Route::post('/tiendas/{tienda}/agente/token', [TiendaController::class, 'tokenAgente'])
        ->name('tiendas.agente.token');
    Route::post('/tiendas/{tienda}/principal', [TiendaController::class, 'principal'])
        ->name('tiendas.principal');

    Route::resource('tiendas', TiendaController::class);

    /* ─────────── Reportes ─────────── */
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    /* RP-EXP-001 · TBL-ENV-001 · exportar y enviar */
    Route::get('/reports/exportes', [ReportExportController::class, 'index'])->name('reports.exports.index');
    Route::post('/reports/exportes', [ReportExportController::class, 'store'])->middleware('throttle:20,1')->name('reports.exports.store');
    Route::get('/reports/exportes/{export}/descargar', [ReportExportController::class, 'descargar'])->name('reports.exports.descargar');
    Route::post('/reports/exportes/{export}/enviar', [ReportExportController::class, 'enviar'])->middleware('throttle:20,1')->name('reports.exports.enviar');

    /* ─────────── Notificaciones (SA-NOT-001) ─────────── */
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notifications.index');
    Route::get('/notificaciones/enviar', [NotificacionController::class, 'create'])->name('notifications.create');
    Route::post('/notificaciones/enviar', [NotificacionController::class, 'store'])->middleware('throttle:10,1')->name('notifications.store');
    Route::post('/notificaciones/leer-todas', [NotificacionController::class, 'leerTodas'])->name('notifications.leer-todas');
    Route::get('/notificaciones/{id}/leer', [NotificacionController::class, 'leer'])->whereUuid('id')->name('notifications.leer');

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

        /* Dar de alta a un cliente sin pasar por la demostración pública. */
        Route::get('/nuevo', [SistemaController::class, 'nuevo'])->name('nuevo');
        Route::post('/nuevo', [SistemaController::class, 'crear'])->name('crear');

        /* SA-PLN-001 · Catálogo de planes. */
        Route::resource('planes', PlanCatalogoController::class)
            ->parameters(['planes' => 'plan'])->except('show');
        Route::post('planes/{plan}/estado', [PlanCatalogoController::class, 'estado'])->name('planes.estado');

        /* SA-PER-001 · Catálogo de permisos. */
        Route::resource('permisos', PermisoController::class)
            ->parameters(['permisos' => 'permiso'])->except('show');

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

// Portal público de clientes (B2C).
require __DIR__.'/tienda.php';
