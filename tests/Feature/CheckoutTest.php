<?php

namespace Tests\Feature;

use App\Models\Pedido;
use App\Models\Presentacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/checkout');

        $response->assertRedirect('/login');
    }

    public function test_checkout_redirects_when_cart_is_empty(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/checkout');

        $response->assertRedirect(route('productos.index'));
    }

    public function test_checkout_creates_pedido_and_decrements_stock(): void
    {
        $user = User::factory()->create();
        $presentacion = Presentacion::factory()->create(['stock' => 10, 'precio' => 1000]);

        $response = $this->actingAs($user)
            ->withSession(['cart' => [(string) $presentacion->id => 3]])
            ->post('/checkout', ['entrega' => 'retiro']);

        $pedido = Pedido::first();

        $this->assertNotNull($pedido);
        $response->assertRedirect(route('checkout.confirmacion', $pedido->id));
        $this->assertEquals(3000, (float) $pedido->total);
        $this->assertEquals(1, $pedido->items()->count());
        $this->assertEquals(7, $presentacion->fresh()->stock);
        $this->assertFalse(session()->has('cart'));
    }

    public function test_checkout_fails_and_rolls_back_when_stock_is_insufficient(): void
    {
        $user = User::factory()->create();
        $presentacion = Presentacion::factory()->create(['stock' => 2]);

        // El carrito quedó desactualizado (stock bajó después de agregarlo).
        $response = $this->actingAs($user)
            ->withSession(['cart' => [(string) $presentacion->id => 5]])
            ->post('/checkout', ['entrega' => 'retiro']);

        $response->assertSessionHasErrors('cantidad');
        $this->assertEquals(0, Pedido::count());
        $this->assertEquals(2, $presentacion->fresh()->stock);
    }
}
