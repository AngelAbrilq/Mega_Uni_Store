# MEGA UNI STORE

Sistema de gestión para una tienda universitaria, construido con **Laravel 13** y **MySQL**.

Cubre el ciclo completo del negocio: se le compra a un proveedor, entra a bodega,
se vende en el punto de venta, se cobra por varios medios, se cuadra la caja y se
mide el resultado — con el kardex y la auditoría respaldando cada movimiento.

---

## Puesta en marcha

Con Laragon corriendo (Apache + MySQL), desde `C:\Laragon\www\Mega_Uni_Store`:

```bash
composer install
copy .env.example .env          # solo la primera vez
php artisan key:generate        # solo la primera vez
php artisan storage:link        # solo la primera vez (imágenes)
php artisan migrate:fresh --seed
php artisan serve
```

`migrate:fresh --seed` **borra y vuelve a crear** todas las tablas y las llena con
datos de trabajo. Si no quieres perder lo que tienes, usa `php artisan migrate --seed`.

Si alguna vez te quedas sin poder entrar (base vacía, contraseña perdida, un
usuario sin rol), este comando te devuelve el acceso sin tocar nada más:

```bash
php artisan mus:admin angelnicolasabrilq@gmail.com --password=loquesea
```

### Cuentas creadas por el seeder

Todas usan la contraseña `password`.

| Correo | Rol | Qué puede hacer |
|---|---|---|
| `angelnicolasabrilq@gmail.com` | Superadministrador | Todo, sin excepciones |
| `admin@megaunistore.test` | Administrador | Todo el negocio y los usuarios |
| `supervisor@megaunistore.test` | Supervisor | Consulta todo, anula ventas, ajusta inventario |
| `cajero@megaunistore.test` | Cajero | Vende, abre y cierra caja |
| `vendedor@megaunistore.test` | Vendedor | Vende y gestiona clientes |
| `bodega@megaunistore.test` | Bodeguero | Compras e inventario |
| `reportes@megaunistore.test` | Reportero | Solo lectura |

---

## Módulos

**Operación diaria**

- **Punto de venta** — búsqueda instantánea por nombre, SKU o código de barras
  (funciona con lector), carrito con control de existencias, cobro con varios
  medios a la vez, cálculo de cambio y atajos `F2` / `F9`.
- **Ventas** — historial filtrable, detalle con utilidad por venta, recibo
  imprimible en formato tirilla y anulación que devuelve la mercancía al inventario.
- **Devoluciones** — parciales, renglón por renglón: se elige cuántas unidades
  vuelven, se calcula el reembolso proporcional con su impuesto y se decide si la
  mercancía reingresa al inventario o se da de baja.
- **Caja** — turnos con base inicial, desglose por medio de pago, arqueo al cierre
  que compara lo esperado contra lo contado y **cierre Z imprimible**.
- **Compras** — pedido al proveedor en borrador y entrada de mercancía que sube el
  stock, escribe el kardex y actualiza los costos.
- **Inventario** — kardex completo y por producto, con saldo corriendo, y ajustes
  manuales (conteo físico, entrada o salida) siempre con motivo.

**Catálogo y terceros**

Productos (con SKU, código de barras, foto, impuesto, proveedor, existencias y punto
de reorden), categorías en árbol, unidades de medida, impuestos, medios de pago,
atributos, clientes y proveedores.

**Dirección**

- **Reportes** — ventas por día, productos más vendidos, ingresos por categoría,
  distribución de medios de pago, desempeño por vendedor, inventario valorizado y
  productos sin rotación.
- **Avisos** — la campana del encabezado agrupa lo agotado, lo que está bajo el
  mínimo, las compras en borrador y las cajas que quedaron abiertas de otro día.
- **Búsqueda global** — `Ctrl+K` busca en productos, clientes, ventas y compras,
  y solo muestra lo que el usuario tiene permiso de ver.
- **Usuarios y roles** — 7 roles y 56 permisos, asignables desde la interfaz.
- **Auditoría** — quién creó, modificó o eliminó cada registro, con el valor antes
  y después, la IP y el navegador.
- **Configuración** — datos del negocio que salen impresos en el recibo.

---

## Qué queda sembrado

| Tabla | Registros |
|---|---|
| Roles / permisos | 7 / 56 |
| Usuarios | 7 |
| Unidades de medida | 13 |
| Impuestos | 6 |
| Medios de pago | 8 |
| Atributos | 8 |
| Categorías | 6 raíz + 19 subcategorías |
| Proveedores | 10 |
| Clientes | 30 |
| Productos | 72 |
| Ventas | ~400 repartidas en 60 días |
| Turnos de caja | ~51, uno abierto |
| Movimientos de kardex | ~1.000 |

---

## Estructura

```
app/
├── Console/Commands/          mus:admin · mus:stock-bajo · mus:respaldo
├── Http/Controllers/          22 de negocio + 9 de autenticación
├── Models/                    20 modelos con relaciones, scopes y accesores
│   └── Concerns/               Auditable · ConImagen (fotos) · ConSlug (direcciones)
├── Services/                  la lógica que no cabe en un controlador
│   ├── StockService           único punto por el que se mueve el inventario
│   ├── SaleService            registrar y anular ventas, en transacción
│   ├── ReturnService          devoluciones parciales y reembolso proporcional
│   ├── PurchaseService        registrar y recibir compras
│   ├── AlertService           lo que aparece en la campana
│   └── ConsecutivoService     numeración a prueba de dos cajeros a la vez
└── Providers/

database/
├── migrations/                27 migraciones · 19 tablas de negocio
└── seeders/                   8 seeders encadenados desde DatabaseSeeder

resources/views/               120 vistas Blade
├── layouts/app.blade.php      shell: menú lateral, animaciones, avisos, Ctrl+K
├── components/mus/            15 componentes propios
├── pos/ sales/ cash/          operación diaria
├── purchases/ inventory/      abastecimiento
├── reports/ audit/ settings/  dirección
└── errors/                    403, 404, 419, 500, 503

tests/
├── Feature/RolesYPermisosTest.php
├── Feature/ProductoTest.php
└── Unit/ProductoCalculosTest.php
```

---

## Decisiones que vale la pena conocer

**El inventario solo se mueve por `StockService`.** Ningún controlador escribe
`products.stock` a mano. El servicio bloquea la fila del producto, ajusta el saldo
y escribe el movimiento de kardex en la misma transacción. Si dos cajeros venden el
mismo artículo a la vez, uno espera al otro.

**Las ventas guardan copia de todo.** Nombre, SKU, precio, costo y tarifa de
impuesto se copian al renglón en el momento de vender. Si mañana cambia el precio
del producto, la factura de ayer sigue diciendo lo que decía ayer.

**Nada se borra de verdad.** Las ventas se anulan (y devuelven la mercancía), los
productos usan borrado lógico y la auditoría guarda el registro completo de lo que
se eliminó.

**Los permisos se declaran en el controlador**, no en las rutas, con el método
estático `middleware()` de Laravel. Cada acción exige el suyo:

```php
public static function middleware(): array
{
    return [
        new Middleware('permission:productos.ver',      only: ['index', 'show']),
        new Middleware('permission:productos.crear',    only: ['create', 'store']),
        new Middleware('permission:productos.editar',   only: ['edit', 'update']),
        new Middleware('permission:productos.eliminar', only: ['destroy']),
    ];
}
```

El menú lateral y los botones se pintan solo si el usuario tiene el permiso, así que
nadie ve una puerta que le va a dar 403.

**Las cantidades admiten decimales.** `products.stock` y `min_stock` son
`decimal(12,3)`, igual que los renglones de venta, los de compra y el kardex. Antes
eran enteros: el día que se vendiera medio kilo de café, el kardex habría guardado
0,500 y la existencia del producto habría truncado a 0 — dos verdades peleando. Se
imprimen con `$producto->stock_texto`, que muestra «180» o «0,5» según haga falta,
sin ceros de relleno.

**Los consecutivos salen de una tabla de contadores.** V-000001, C-000001 y D-000001
los entrega `ConsecutivoService` con un `UPDATE counters SET value = value + 1`, que
bloquea la fila y deja que la base serialice. Antes salían de `max(id) + 1`: si dos
cajeros cobraban en el mismo segundo, los dos leían el mismo máximo y el segundo
chocaba contra el índice único. Es de esos errores que nunca aparecen probando solo.

**El cliente entra por otra puerta.** `customers` es `Authenticatable` y tiene su
propio guard (`cliente`), separado del guard `web` del panel. No es un rol más: un
cliente **no existe** para el panel, así que la seguridad deja de depender de que
ningún `can()` esté mal escrito. Y como es la misma tabla que usa el cajero, quien se
registra en línea es el mismo registro que compra en el mostrador.

**Nada sale a internet por accidente.** `products.is_public` nace en `false` y hay un
interruptor en el formulario. El catálogo público usa su propio scope (`->publico()`)
y nunca reutiliza las consultas del panel: si mañana se agrega un campo interno, no
se filtra solo.

**«Nuevo …» abre una ventana, no otra página.** Los formularios de alta se
piden por `fetch` a la MISMA ruta de siempre (`products.create`, `customers.create`…);
el layout detecta la cabecera `X-Mus-Modal` y devuelve solo el formulario, sin menú
ni barra superior. Si el JavaScript falla, si alguien abre el enlace en otra pestaña
o si un buscador entra directo, la página completa sigue existiendo y funcionando
igual que antes: el modal es una mejora encima, no un reemplazo. Los errores de
validación se repintan dentro de la misma ventana sin perder lo escrito.

**Las cifras tienen una sola forma en todo el sistema.** Números con cifras de ancho
fijo (`tabular-nums`), así una columna de precios queda alineada al píxel; el signo
`$` más pequeño y en gris, porque es contexto y no dato; y los códigos —SKU, número
de venta, NIT— en monoespaciada. Repetido en las 121 vistas, ese detalle es lo que
hace que el sistema se lea como hecho a propósito.

**Se usa igual desde un celular.** No hay una versión móvil aparte: por
debajo de 760 px de ancho las tablas dejan de ser tablas y cada fila se
vuelve una tarjeta «etiqueta → valor», los formularios pasan a una columna,
los campos suben a 16 px (menos que eso y el navegador hace zoom solo), los
botones crecen al tamaño de un dedo y el punto de venta cobra desde una
barra fija al pie. La etiqueta de cada tarjeta la pone un JavaScript que
copia el encabezado de la columna, así que vale para las 25 tablas del
sistema y también para las filas que se agregan sobre la marcha.

**Las imágenes van al disco, no a la base de datos.** El archivo se guarda en
`storage/app/public` y en la columna (`image_url`, o `avatar_url` en usuarios) queda
únicamente la ruta, en un VARCHAR. Guardar la foto dentro de la base la infla, hace
lentas las consultas y vuelve enorme el respaldo; guardando la ruta, el `.sql` sigue
pesando kilobytes. Tienen foto los **productos, las categorías, los clientes, los
proveedores y los usuarios**, todos por el mismo camino: el trait `ConImagen` en el
modelo y `GuardaImagen` en el controlador.

**Ninguna foto se deforma.** El componente `<x-mus.avatar>` dibuja un cuadro de lado
fijo y ancla la imagen a los cuatro lados con `object-fit:cover`: la foto se recorta
y se centra, nunca se estira. Da igual si la suben vertical, horizontal o cuadrada,
en JPG, PNG o WEBP. Cuando no hay foto pinta las iniciales sobre un color sacado del
propio nombre, así que el mismo cliente siempre se ve del mismo color.

---

## Pruebas

```bash
php artisan test
```

Corren contra SQLite en memoria: **no tocan la base de datos de Laragon**.

---

## Respaldos y tareas programadas

```bash
php artisan mus:respaldo              # copia comprimida en storage/app/respaldos
php artisan mus:respaldo --dias=30    # conservar 30 días en vez de 14
php artisan mus:stock-bajo            # qué hay que reponer hoy
```

El respaldo usa `mysqldump` si está instalado (Laragon lo trae) y, si no lo
encuentra, hace el volcado con PHP puro: funciona igual en cualquier equipo.
Para restaurar, se descomprime el `.gz` y se ejecuta:

```bash
mysql -u root mega_uni_store < mega_uni_store_2026-08-20_233000.sql
```

Para que el respaldo corra solo cada noche a las 23:30, hay que crear **una**
tarea en el Programador de tareas de Windows que se repita cada minuto:

| Campo | Valor |
|---|---|
| Programa | `C:\laragon\bin\php\php-8.4\php.exe` |
| Argumentos | `artisan schedule:run` |
| Iniciar en | `C:\Laragon\www\Mega_Uni_Store` |
| Desencadenador | Diario, repetir cada 1 minuto durante 1 día |

Laravel se encarga del resto: mira `routes/console.php` y decide qué toca.

---

## Lo que todavía no está

1. **Ventas a crédito y cartera** — todas las ventas se cobran de contado.
2. **Valores de atributos y variantes** — existe «Color», falta «rojo / azul» y
   los productos con talla y color como referencias distintas.
3. **Facturación electrónica DIAN** — resolución, CUFE y envío; exige comprar un
   certificado digital y contratar un proveedor tecnológico.
4. **Nómina y contabilidad** — el sistema anterior las tenía; requieren decidir
   primero si entran en el alcance de este proyecto.
