<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Pedido;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laravel arma la clave del tope de peticiones con el id del usuario a secas,
 * sin la ruta. O sea que el "30 por minuto" del carrito y el "10 por minuto"
 * del checkout contaban en el mismo balde: el cliente que tocaba diez veces el
 * más/menos en el carrito ya no podía confirmar el pedido — le salía un error
 * y se perdía la venta. Con topes con nombre, cada uno cuenta lo suyo.
 */
class TopesPorSeparadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_tocar_el_carrito_no_deja_sin_checkout(): void
    {
        $producto = Producto::factory()->create([
            'marca_id' => Marca::factory()->create()->id,
            'categoria_id' => Categoria::factory()->create()->id,
        ]);
        $presentacion = Presentacion::factory()->create([
            'producto_id' => $producto->id, 'precio' => 1000, 'stock' => 99, 'activo' => true,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'cliente']));

        // Un cliente indeciso: doce toques al más y al menos.
        for ($i = 0; $i < 12; $i++) {
            $this->patch('/carrito/update', ['presentacion_id' => $presentacion->id, 'cantidad' => 1])
                ->assertStatus(302);
        }

        // Y ahora quiere confirmar. Antes acá llegaba un 429.
        $this->post('/carrito/add', ['presentacion_id' => $presentacion->id, 'cantidad' => 1]);

        $this->post('/checkout', ['entrega' => 'retiro'])
            ->assertStatus(302)
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Pedido::count(), 'No se creó el pedido.');
    }

    /**
     * El tope sigue existiendo: lo que cambió es que cuenta por separado.
     */
    public function test_el_checkout_sigue_teniendo_su_propio_tope(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cliente']));

        $ultimo = null;

        for ($i = 0; $i < 12; $i++) {
            $ultimo = $this->post('/checkout', ['entrega' => 'retiro']);
        }

        $this->assertSame(429, $ultimo->status(), 'El checkout se quedó sin tope propio.');
    }
}
