# 📋 Changelog — Mega Uni Store v3

Historial de cambios técnicos relevantes. Los bugs menores sin impacto en arquitectura no se registran aquí.

---

## [3.0.5] — mayo 2026

### Módulo de Contraseñas — PasswordReset.php

**Bug crítico resuelto:** Fatal error `Table 'password_resets' doesn't exist` al acceder a cualquier ruta del módulo de contraseñas.

**Causa raíz:** El modelo `PasswordReset` ejecutaba queries en su constructor sin verificar si las tablas existían.

**Solución aplicada:**
- Se agregó `CREATE TABLE IF NOT EXISTS` para `password_resets` y `solicitudes_cambio_contrasena` en el constructor del modelo
- Se reemplazó `ur.es_principal = 1` (columna inexistente) por subquery NOT EXISTS para determinar el rol principal
- Se corrigió `r.rol_nombre` → `r.nombre AS rol_nombre` (nombre real de la columna en `roles`)

**Archivos modificados:** `backend/app/models/PasswordReset.php`

---

## [3.0.4] — mayo 2026

### Dashboard — Charts vacíos

**Bug:** Los dashboards mostraban error de JavaScript cuando no había ventas en los últimos 7 días o top productos vacío.

**Causa:** Chart.js intentaba renderizar datasets con arrays vacíos sin validación previa.

**Solución:** Se agregó validación de arrays vacíos antes de inicializar Chart.js; se muestra estado vacío con icono y mensaje descriptivo.

**Archivos modificados:** Todas las vistas `dashboard/*.php` de cada rol.

---

## [3.0.3] — mayo 2026

### Inventario — Filtros en movimientos

**Mejora:** Se agregaron filtros de tipo, fecha inicio y fecha fin a las vistas `inventario/movimiento.php` y `inventario/movimientos.php`.

**Implementación:**
- `InventarioController::movimiento()` acepta ahora `$filtroTipo`, `$filtroDesde`, `$filtroHasta` desde `$_GET`
- `Inventario::listarMovimientos()` extendido con 5 parámetros: `$itemId`, `$tiendaId`, `$tipo`, `$desde`, `$hasta`
- Los filtros se aplican como cláusulas `WHERE` adicionales en SQL

**Archivos modificados:** `InventarioController.php`, `Inventario.php` (model), `inventario/movimiento.php`, `inventario/movimientos.php`

---

## [3.0.2] — mayo 2026

### Ventas — "Ver" abre modal en lugar de navegación SPA

**Cambio de comportamiento:** El botón "Ver" en `ventas/index.php` ahora abre el comprobante en un **modal** en lugar de cargar la vista completa en el panel SPA.

**Antes:** `loadContent('ventas.show&id=X', true)` — reemplazaba el panel con el comprobante completo

**Ahora:** `openModal('index.php?route=ventas.show&id=X&ajax=1')` — modal overlay sobre el listado

**Motivo:** Permite revisar el detalle sin perder el contexto del listado de ventas.

**Helpers en ventas/show.php:**
```javascript
function _ventaShowVolver() { if (modal open) closeModal(); else loadContent('ventas.index', true); }
function _ventaShowDevolucion(id) { if (modal open) closeModal(); loadContent('devoluciones.create&venta_id='+id, true); }
```

**Archivos modificados:** `ventas/index.php`, `ventas/show.php`

---

## [3.0.1] — mayo 2026

### Navegación SPA — Reportero y Nómina/RRHH

**Bug:** Los dashboards de roles Reportero y Nómina/RRHH usaban `<a href="...">` en lugar de `onclick="loadContent(...)"`, lo que causaba recargas completas de página en lugar de navegación SPA.

**Solución:** Se reemplazaron todos los `<a href>` de accesos rápidos en los dashboards de Reportero y Nómina por llamadas `loadContent()`.

**Archivos modificados:** `dashboard/reportero.php`, `dashboard/nomina.php`

---

## [3.0.0] — abril–mayo 2026

### Versión inicial del proyecto Fase 3

Implementación completa del sistema multitienda incluyendo:

- **Arquitectura MVC** con Front Controller (`web.php`) y SPA-lite (`loadContent()`)
- **Sistema de roles y permisos** basado en `roles → rol_permisos → permisos`
- **9 roles** configurados: Superadmin, Admin Tienda, Supervisor, Vendedor, Bodeguero, Reportero, Nómina, Cliente, Sistema
- **Módulos:** Tiendas, Usuarios, Categorías, Unidades, Impuestos, Productos, Inventario, Ventas, Caja, Clientes, Empleados, Proveedores, Cupones, Devoluciones, Reportes, Setup
- **Módulo de contraseñas** con Flujo A (reset por email), Flujo C (aprobación admin), Flujo D (admin directo)
- **Sistema de modales** para formularios de creación/edición via `openModal()` / `closeModal()`
- **Chart.js 4.4.0** cargado globalmente en dashboard_layout.php
- **CSRF protection** en todos los formularios POST

---

*Changelog mantenido desde mayo 2026 — Ángel Nicolás Abril*
