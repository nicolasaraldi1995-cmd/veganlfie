<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\Presentacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PedidoClienteTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_their_pedido(): void
    {
        $user = User::factory()->create();
        $pedido = Pedido::factory()->for($user)->create();

        $response = $this->actingAs($user)->get("/mis-pedidos/{$pedido->id}");

        $response->assertOk();
    }

    public function test_other_user_cannot_view_pedido(): void
    {
        $owner = User::factory()->create();
        $otro = User::factory()->create();
        $pedido = Pedido::factory()->for($owner)->create();

        $response = $this->actingAs($otro)->get("/mis-pedidos/{$pedido->id}");

        $response->assertForbidden();
    }

    public function test_other_user_cannot_modify_pedido_items(): void
    {
        $owner = User::factory()->create();
        $otro = User::factory()->create();
        $pedido = Pedido::factory()->for($owner)->create();
        $presentacion = Presentacion::factory()->create();

        $response = $this->actingAs($otro)->post("/mis-pedidos/{$pedido->id}/item", [
            'presentacion_id' => $presentacion->id,
            'cantidad' => 1,
        ]);

        $response->assertForbidden();
    }

    public function test_adding_item_reserves_stock(): void
    {
        $user = User::factory()->create();
        $pedido = Pedido::factory()->for($user)->create(['estado' => 'pending']);
        $presentacion = Presentacion::factory()->create(['stock' => 5, 'precio' => 100]);

        $this->actingAs($user)->post("/mis-pedidos/{$pedido->id}/item", [
            'presentacion_id' => $presentacion->id,
            'cantidad' => 2,
        ]);

        $this->assertEquals(3, $presentacion->fresh()->stock);
        $this->assertEquals(200, (float) $pedido->fresh()->total);
    }

    public function test_removing_item_restores_stock(): void
    {
        $user = User::factory()->create();
        $pedido = Pedido::factory()->for($user)->create(['estado' => 'pending']);
        $presentacion = Presentacion::factory()->create(['stock' => 5, 'precio' => 100]);

        $this->actingAs($user)->post("/mis-pedidos/{$pedido->id}/item", [
            'presentacion_id' => $presentacion->id,
            'cantidad' => 2,
        ]);
        $this->assertEquals(3, $presentacion->fresh()->stock);

        $this->actingAs($user)->delete("/mis-pedidos/{$pedido->id}/item", [
            'presentacion_id' => $presentacion->id,
        ]);

        $this->assertEquals(5, $presentacion->fresh()->stock);
    }

    public function test_cannot_modify_non_editable_pedido(): void
    {
        $user = User::factory()->create();
        $pedido = Pedido::factory()->for($user)->create(['estado' => 'confirmed']);
        $presentacion = Presentacion::factory()->create();

        $response = $this->actingAs($user)->post("/mis-pedidos/{$pedido->id}/item", [
            'presentacion_id' => $presentacion->id,
            'cantidad' => 1,
        ]);

        $response->assertSessionHasErrors('pedido');
        $this->assertEquals(0, $pedido->items()->count());
    }
}
