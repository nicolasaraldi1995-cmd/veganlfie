<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\PedidoResource\Pages\EditPedido;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * precio_unitario y subtotal están ocultos para operador (isAdmin() only) en el
 * repeater de items. Filament no dehidrata un campo oculto salvo que se declare
 * dehydratedWhenHidden(), así que sin eso cualquier guardado de un operador
 * descartaba el subtotal recién calculado (bug real: el cliente terminaba
 * facturado por la cantidad vieja) o directamente rompía con un NOT NULL al
 * agregar un producto nuevo al pedido.
 */
class PedidoResourceOperadorTest extends TestCase
{
    use RefreshDatabase;

    public function test_operador_puede_cambiar_la_cantidad_de_un_item_y_el_subtotal_se_recalcula(): void
    {
        $operador = User::factory()->create(['role' => 'operador']);
        $cliente = User::factory()->create(['role' => 'cliente']);
        $presentacion = Presentacion::factory()->create(['precio' => 100, 'stock' => 500]);
        $pedido = Pedido::factory()->create(['user_id' => $cliente->id, 'estado' => 'pending', 'total' => 200]);
        $item = PedidoItem::create([
            'pedido_id' => $pedido->id,
            'presentacion_id' => $presentacion->id,
            'cantidad' => 2,
            'precio_unitario' => 100,
            'subtotal' => 200,
        ]);

        $component = Livewire::actingAs($operador)->test(EditPedido::class, ['record' => $pedido->id]);
        $data = $component->get('data');
        $key = array_key_first($data['items']);

        $component->set("data.items.{$key}.cantidad", 5)->call('save')->assertHasNoErrors();

        $item->refresh();
        $this->assertEquals(5, $item->cantidad);
        $this->assertEquals(500, $item->subtotal);
        $this->assertEquals(500, $pedido->fresh()->total);
    }

    public function test_operador_puede_agregar_un_producto_nuevo_al_pedido(): void
    {
        $operador = User::factory()->create(['role' => 'operador']);
        $cliente = User::factory()->create(['role' => 'cliente']);
        $presentacion = Presentacion::factory()->create(['precio' => 250, 'stock' => 500]);
        $pedido = Pedido::factory()->create(['user_id' => $cliente->id, 'estado' => 'pending', 'total' => 0]);

        // Se setean los 4 campos (no solo presentacion_id) para simular el estado
        // que ya deja el afterStateUpdated reactivo cuando un usuario real elige
        // el producto en el desplegable, sin depender de que el test dispare esa
        // cadena reactiva por sí solo.
        Livewire::actingAs($operador)->test(EditPedido::class, ['record' => $pedido->id])
            ->set('data.items.nuevo.presentacion_id', $presentacion->id)
            ->set('data.items.nuevo.cantidad', 3)
            ->set('data.items.nuevo.precio_unitario', 250)
            ->set('data.items.nuevo.subtotal', 750)
            ->call('save')
            ->assertHasNoErrors();

        $item = PedidoItem::where('pedido_id', $pedido->id)->first();
        $this->assertNotNull($item);
        $this->assertEquals(3, $item->cantidad);
        $this->assertEquals(250, $item->precio_unitario);
        $this->assertEquals(750, $item->subtotal);
        $this->assertEquals(750, $pedido->fresh()->total);
    }

    public function test_operador_no_ve_el_precio_en_el_selector_de_productos_del_pedido(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operador = User::factory()->create(['role' => 'operador']);
        $cliente = User::factory()->create(['role' => 'cliente']);
        $presentacion = Presentacion::factory()->create(['precio' => 54321, 'stock' => 10]);
        $pedido = Pedido::factory()->create(['user_id' => $cliente->id, 'estado' => 'pending']);
        PedidoItem::create([
            'pedido_id' => $pedido->id,
            'presentacion_id' => $presentacion->id,
            'cantidad' => 1,
            'precio_unitario' => 54321,
            'subtotal' => 54321,
        ]);

        // El repeater necesita al menos un item cargado para renderizar el
        // Select de "presentacion_id" -- si no, no hay nada que ver para nadie.
        Livewire::actingAs($admin)->test(EditPedido::class, ['record' => $pedido->id])
            ->assertSee('54321');

        Livewire::actingAs($operador)->test(EditPedido::class, ['record' => $pedido->id])
            ->assertDontSee('54321');
    }
}
