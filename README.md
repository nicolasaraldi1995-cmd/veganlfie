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

## Backups

`php artisan backup:database` genera un dump comprimido (`.sql.gz`) de la base MySQL en `storage/app/backups/` (nunca se sube a git — son datos reales de clientes) y borra automáticamente los backups más viejos que los últimos 14 (`--keep=N` para cambiar la cantidad).

```bash
php artisan backup:database
```

**Activar el backup diario automático (Windows/Laragon local):** correr una sola vez, con Laragon cerrado o abierto, en una terminal:

```powershell
schtasks /create /tn "VeganLife DB Backup" /tr "\"C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe\" \"C:\laragon\www\veganlife\artisan\" backup:database" /sc daily /st 20:00 /f
```

Esto corre el backup todos los días a las 20:00 **si la PC está prendida y Laragon (MySQL) está corriendo** en ese momento — cambiá `/st 20:00` por el horario que más te convenga. Para desactivarlo: `schtasks /delete /tn "VeganLife DB Backup" /f`.

**En un hosting real** (Linux con cron), no hace falta el paso anterior: alcanza con la entrada de cron estándar de Laravel corriendo cada minuto —

```
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

— porque el backup diario ya está registrado en `routes/console.php` (`Schedule::command('backup:database')->dailyAt('03:00')`).

**Restaurar un backup** (reemplaza todo el contenido actual de la base — usar con cuidado):

```bash
gzip -dc storage/app/backups/veganlife-2026-07-12_20-00-00.sql.gz | mysql -u root veganlife
```

## Notas de arquitectura

- El stock de `Presentacion` se reserva/libera automáticamente vía `PedidoItemObserver` cada vez que se crea, actualiza o elimina un `PedidoItem` (checkout, autoservicio del cliente en "Mis pedidos", o edición desde el panel admin). Al cancelar un pedido desde el panel, `Pedido::restaurarStock()` devuelve las unidades reservadas.
- La lógica de carrito (sesión) vive en `App\Services\CartService`, compartida entre `CartController` y `CheckoutController`.
- Pagos (`Pago`) son registros manuales (efectivo, transferencia, MercadoPago informado) — no hay integración con una pasarela de pago online todavía.
