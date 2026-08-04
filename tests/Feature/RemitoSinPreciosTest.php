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
 * La ficha del pedido le esconde al operador el precio, el subtotal y el total
 * (ocho guardas entre PedidoResource, ViewPedido y el infolist de ítems). Pero
 * el botón "Descargar remito" de esa misma ficha, que el operador sí ve, le
 * daba el PDF con todo eso adentro.
 *
 * Se mira el HTML que arma la plantilla y no los bytes del PDF: dompdf comprime
 * los flujos de texto, así que en el archivo terminado los números no aparecen
 * como texto buscable.
 */
class RemitoSinPreciosTest extends TestCase
{
    use RefreshDatabase;

    private const PRECIO = 4321.55;

    private function pedidoDe(User $cliente): Pedido
    {
        $producto = Producto::factory()->create([
            'nombre' => 'Producto del remito',
            'marca_id' => Marca::factory()->create()->id,
            'categoria_id' => Categoria::factory()->create()->id,
        ]);

        $presentacion = Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'unidad' => '1kg',
            'precio' => self::PRECIO,
            'stock' => 50,
            'activo' => true,
        ]);

        $pedido = Pedido::factory()->create(['user_id' => $cliente->id, 'estado' => 'pending']);

        PedidoItem::create([
            'pedido_id' => $pedido->id,
            'presentacion_id' => $presentacion->id,
            'cantidad' => 2,
            'precio_unitario' => self::PRECIO,
            'subtotal' => self::PRECIO * 2,
        ]);

        $pedido->recalcularTotal();

        return $pedido->fresh()->load('items.presentacion.producto.marca');
    }

    private function remitoQueVe(User $quien, Pedido $pedido): string
    {
        $this->actingAs($quien);

        return view('pdf.pedido', ['pedido' => $pedido])->render();
    }

    public function test_el_operador_no_ve_precios_en_el_remito(): void
    {
        $pedido = $this->pedidoDe(User::factory()->create(['role' => 'cliente']));

        $remito = $this->remitoQueVe(User::factory()->create(['role' => 'operador']), $pedido);

        foreach (['4.321,55', '8.643,10', 'Precio', 'Subtotal'] as $dato) {
            $this->assertStringNotContainsString(
                $dato,
                $remito,
                "El remito del operador no tendría que traer «{$dato}»."
            );
        }

        // Lo que sí necesita para preparar y entregar el pedido.
        $this->assertStringContainsString('Producto del remito', $remito);
        $this->assertStringContainsString('Cant', $remito);
    }

    public function test_el_cliente_si_ve_los_precios_de_su_pedido(): void
    {
        $cliente = User::factory()->create(['role' => 'cliente']);

        $remito = $this->remitoQueVe($cliente, $this->pedidoDe($cliente));

        $this->assertStringContainsString('4.321,55', $remito, 'Es su comprobante: tiene que ver lo que paga.');
        $this->assertStringContainsString('8.643,10', $remito);
    }

    public function test_el_admin_si_ve_los_precios(): void
    {
        $pedido = $this->pedidoDe(User::factory()->create(['role' => 'cliente']));

        $remito = $this->remitoQueVe(User::factory()->create(['role' => 'admin']), $pedido);

        $this->assertStringContainsString('4.321,55', $remito);
    }

    /**
     * El operador sigue pudiendo bajar el remito: lo necesita para armar el
     * pedido. Lo que cambia es qué trae adentro.
     */
    public function test_el_operador_sigue_pudiendo_descargar_el_remito(): void
    {
        $pedido = $this->pedidoDe(User::factory()->create(['role' => 'cliente']));

        $this->actingAs(User::factory()->create(['role' => 'operador']))
            ->get("/mis-pedidos/{$pedido->id}/pdf")
            ->assertOk();
    }

    /**
     * Y un cliente sigue sin poder mirar el pedido de otro.
     */
    public function test_un_cliente_no_baja_el_remito_de_otro(): void
    {
        $pedido = $this->pedidoDe(User::factory()->create(['role' => 'cliente']));

        $this->actingAs(User::factory()->create(['role' => 'cliente']))
            ->get("/mis-pedidos/{$pedido->id}/pdf")
            ->assertForbidden();
    }
}
