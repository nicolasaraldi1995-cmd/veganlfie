<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El cliente edita su propio pedido pendiente desde "Mis pedidos". Ese camino
 * había quedado afuera del arreglo de "lo dado de baja no se vende": validaba
 * con exists, que no mira si la presentación está activa, ni el precio, ni si
 * el producto sigue publicado. Con un POST directo entraba cualquier cosa.
 */
class AutoservicioPedidoTest extends TestCase
{
    use RefreshDatabase;

    private User $cliente;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cliente = User::factory()->create(['role' => 'cliente']);
        $this->pedido = Pedido::factory()->create([
            'user_id' => $this->cliente->id,
            'estado' => 'pending',
        ]);
    }

    private function presentacion(array $prod = [], array $pres = []): Presentacion
    {
        $producto = Producto::factory()->create(array_merge([
            'marca_id' => Marca::factory()->create()->id,
            'categoria_id' => Categoria::factory()->create()->id,
            'activo' => true,
        ], $prod));

        return Presentacion::factory()->create(array_merge([
            'producto_id' => $producto->id,
            'unidad' => '1u',
            'precio' => 1000,
            'stock' => 50,
            'activo' => true,
        ], $pres));
    }

    private function agregar(Presentacion $p, int $cantidad = 1)
    {
        return $this->actingAs($this->cliente)
            ->post("/mis-pedidos/{$this->pedido->id}/item", [
                'presentacion_id' => $p->id,
                'cantidad' => $cantidad,
            ]);
    }

    public function test_no_puede_agregar_una_presentacion_apagada(): void
    {
        $p = $this->presentacion(pres: ['activo' => false]);

        $this->agregar($p);

        $this->assertSame(0, $this->pedido->items()->count(), 'Entró una presentación apagada.');
    }

    public function test_no_puede_agregar_un_producto_dado_de_baja(): void
    {
        $p = $this->presentacion(prod: ['activo' => false]);

        $this->agregar($p);

        $this->assertSame(0, $this->pedido->items()->count());
    }

    public function test_no_puede_agregar_una_presentacion_a_cero(): void
    {
        $p = $this->presentacion(pres: ['precio' => 0]);

        $this->agregar($p);

        $this->assertSame(0, $this->pedido->items()->count(), 'Se pidió un producto gratis.');
    }

    public function test_si_puede_agregar_algo_publicado(): void
    {
        $p = $this->presentacion();

        $this->agregar($p, 2);

        $item = $this->pedido->items()->firstOrFail();
        $this->assertEquals(1000, $item->precio_unitario);
        $this->assertEquals(2000, $item->subtotal);
    }

    /**
     * El renglón viejo se cobró a su precio. Cambiar la cantidad no puede
     * reprecificarlo: antes el cliente esperaba a que bajara el precio y con un
     * PATCH se rebajaba su propio pedido.
     */
    public function test_cambiar_la_cantidad_no_reprecifica_el_renglon(): void
    {
        $p = $this->presentacion();
        $item = PedidoItem::create([
            'pedido_id' => $this->pedido->id,
            'presentacion_id' => $p->id,
            'cantidad' => 2,
            'precio_unitario' => 1000,   // se cobró a 1000
            'subtotal' => 2000,
        ]);

        // El catálogo baja a 400 hoy.
        $p->update(['precio' => 400]);

        $this->actingAs($this->cliente)
            ->patch("/mis-pedidos/{$this->pedido->id}/item", [
                'presentacion_id' => $p->id,
                'cantidad' => 3,
            ]);

        $item->refresh();
        $this->assertEquals(1000, $item->precio_unitario, 'Se reprecificó al precio de hoy.');
        $this->assertEquals(3000, $item->subtotal);
    }

    /** Bajar la cantidad a cero borra el renglón, como antes. */
    public function test_bajar_a_cero_borra_el_renglon(): void
    {
        $p = $this->presentacion();
        PedidoItem::create([
            'pedido_id' => $this->pedido->id,
            'presentacion_id' => $p->id,
            'cantidad' => 2,
            'precio_unitario' => 1000,
            'subtotal' => 2000,
        ]);

        $this->actingAs($this->cliente)
            ->patch("/mis-pedidos/{$this->pedido->id}/item", [
                'presentacion_id' => $p->id,
                'cantidad' => 0,
            ]);

        $this->assertSame(0, $this->pedido->items()->count());
    }
}
