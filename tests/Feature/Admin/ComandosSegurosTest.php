<?php

namespace Tests\Feature\Admin;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComandosSegurosTest extends TestCase
{
    use RefreshDatabase;

    // --- #25: los comandos no bajan de cualquier URL -----------------------

    public function test_importar_desde_url_rechaza_un_esquema_de_archivo(): void
    {
        $this->artisan('app:importar-productos-desde-url', ['url' => 'file:///etc/passwd'])
            ->assertFailed();
    }

    public function test_importar_desde_url_rechaza_php_filter(): void
    {
        $this->artisan('app:importar-productos-desde-url', ['url' => 'php://filter/resource=.env'])
            ->assertFailed();
    }

    public function test_aplicar_fotos_rechaza_un_esquema_de_archivo(): void
    {
        $this->artisan('app:aplicar-fotos-productos-desde-zip', ['url' => 'file:///etc/passwd'])
            ->assertFailed();
    }

    // --- #16: el comando de migración no toca el disco privado -------------

    public function test_migrar_a_s3_no_migra_desde_el_disco_local(): void
    {
        // 'local' guarda imports/ con los costos del proveedor.
        $this->artisan('imagenes:migrar-a-s3', ['--origen' => 'local', '--destino' => 's3'])
            ->assertFailed();
    }

    // --- #31: los "más vendidos" no cuentan cancelados ---------------------

    public function test_un_pedido_cancelado_no_infla_los_mas_vendidos(): void
    {
        $producto = Producto::factory()->create([
            'marca_id' => Marca::factory()->create()->id,
            'categoria_id' => Categoria::factory()->create()->id,
            'activo' => true,
        ]);
        $presentacion = Presentacion::factory()->create([
            'producto_id' => $producto->id, 'precio' => 1000, 'stock' => 100, 'activo' => true,
        ]);

        // Un pedido con una buena cantidad del producto, que después se cancela.
        $pedido = Pedido::factory()->create([
            'user_id' => User::factory()->create(['role' => 'cliente'])->id,
            'estado' => 'pending',
        ]);
        PedidoItem::create([
            'pedido_id' => $pedido->id,
            'presentacion_id' => $presentacion->id,
            'cantidad' => 50,
            'precio_unitario' => 1000,
            'subtotal' => 50000,
        ]);
        $pedido->update(['estado' => 'canceled']);

        $respuesta = $this->get('/');
        $respuesta->assertOk();

        // El producto no debería estar entre los más vendidos: su única venta
        // está cancelada.
        $masVendidos = collect($respuesta->viewData('page')['props']['masVendidos'] ?? []);
        $this->assertTrue(
            $masVendidos->where('id', $producto->id)->isEmpty(),
            'Un producto de un pedido cancelado apareció en los más vendidos.'
        );
    }
}
