<?php

namespace Tests\Feature;

use App\Models\Presentacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_item_to_cart(): void
    {
        $presentacion = Presentacion::factory()->create(['stock' => 5]);

        $response = $this->post('/carrito/add', [
            'presentacion_id' => $presentacion->id,
            'cantidad' => 2,
        ]);

        $response->assertSessionHas('cart', [(string) $presentacion->id => 2]);
    }

    public function test_adding_more_than_available_stock_fails(): void
    {
        $presentacion = Presentacion::factory()->create(['stock' => 3]);

        $response = $this->post('/carrito/add', [
            'presentacion_id' => $presentacion->id,
            'cantidad' => 5,
        ]);

        $response->assertSessionHasErrors('cantidad');
        $response->assertSessionMissing('cart');
    }

    public function test_adding_twice_accumulates_and_still_respects_stock(): void
    {
        $presentacion = Presentacion::factory()->create(['stock' => 4]);

        $this->post('/carrito/add', ['presentacion_id' => $presentacion->id, 'cantidad' => 3]);
        $response = $this->withSession(['cart' => [(string) $presentacion->id => 3]])
            ->post('/carrito/add', ['presentacion_id' => $presentacion->id, 'cantidad' => 3]);

        $response->assertSessionHasErrors('cantidad');
    }

    public function test_can_update_cart_quantity(): void
    {
        $presentacion = Presentacion::factory()->create(['stock' => 10]);

        $response = $this->withSession(['cart' => [(string) $presentacion->id => 1]])
            ->patch('/carrito/update', [
                'presentacion_id' => $presentacion->id,
                'cantidad' => 4,
            ]);

        $response->assertSessionHas('cart', [(string) $presentacion->id => 4]);
    }

    public function test_updating_beyond_stock_fails(): void
    {
        $presentacion = Presentacion::factory()->create(['stock' => 2]);

        $response = $this->withSession(['cart' => [(string) $presentacion->id => 1]])
            ->patch('/carrito/update', [
                'presentacion_id' => $presentacion->id,
                'cantidad' => 9,
            ]);

        $response->assertSessionHasErrors('cantidad');
    }

    public function test_updating_to_zero_removes_item(): void
    {
        $presentacion = Presentacion::factory()->create(['stock' => 10]);

        $response = $this->withSession(['cart' => [(string) $presentacion->id => 2]])
            ->patch('/carrito/update', [
                'presentacion_id' => $presentacion->id,
                'cantidad' => 0,
            ]);

        $response->assertSessionHas('cart', []);
    }

    public function test_can_remove_item_from_cart(): void
    {
        $presentacion = Presentacion::factory()->create();

        $response = $this->withSession(['cart' => [(string) $presentacion->id => 2]])
            ->delete('/carrito/remove', [
                'presentacion_id' => $presentacion->id,
            ]);

        $response->assertSessionHas('cart', []);
    }
}
