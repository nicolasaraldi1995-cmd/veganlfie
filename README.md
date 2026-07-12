# VeganLife

E-commerce de productos veganos: catálogo público (Inertia + Vue 3) y panel de administración (Filament 3) para gestionar productos, precios, stock, pedidos y pagos.

## Stack

- **Backend:** Laravel 13, PHP 8.3, Sanctum
- **Frontend:** Inertia.js + Vue 3 + Tailwind CSS (Vite)
- **Panel admin:** Filament 3 (`/admin`)
- **Otros:** `barryvdh/laravel-dompdf` (listas de precios y pedidos en PDF), `maatwebsite/excel` (importación de catálogo), `ziggy` (rutas de Laravel disponibles en JS)

## Requisitos

- PHP 8.3+ con extensión `sqlite3` (para tests) y `pdo_mysql`
- MySQL 8+
- Node 20+
- Composer
- [Laragon](https://laragon.org/) (recomendado en Windows: genera automáticamente el dominio local `veganlife.test`)

## Setup local

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Editá `.env` si tu configuración de MySQL difiere de la default (`DB_DATABASE=veganlife`, usuario `root` sin contraseña).

```bash
php artisan migrate --seed
npm run build   # o `npm run dev` para desarrollo con hot reload
```

Con Laragon corriendo (Apache + MySQL), la app queda disponible en `http://veganlife.test`. El panel de administración está en `http://veganlife.test/admin`.

### Correr todo junto (server + queue + logs + vite)

```bash
composer run dev
```

## Roles

- **admin**: acceso completo al panel, incluye precios, costos y pagos.
- **operador**: acceso operativo al panel (stock, pedidos) sin ver precios de costo ni finanzas.

Se asignan en el campo `role` del usuario (`App\Models\User`).

## Tests

Los tests corren contra SQLite en memoria, no requieren MySQL levantado:

```bash
php artisan test
vendor/bin/pint --test   # chequeo de estilo, sin modificar archivos
vendor/bin/pint          # aplica el estilo automáticamente
```

## Notas de arquitectura

- El stock de `Presentacion` se reserva/libera automáticamente vía `PedidoItemObserver` cada vez que se crea, actualiza o elimina un `PedidoItem` (checkout, autoservicio del cliente en "Mis pedidos", o edición desde el panel admin). Al cancelar un pedido desde el panel, `Pedido::restaurarStock()` devuelve las unidades reservadas.
- La lógica de carrito (sesión) vive en `App\Services\CartService`, compartida entre `CartController` y `CheckoutController`.
- Pagos (`Pago`) son registros manuales (efectivo, transferencia, MercadoPago informado) — no hay integración con una pasarela de pago online todavía.
