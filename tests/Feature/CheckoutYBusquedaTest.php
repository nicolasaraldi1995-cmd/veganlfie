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

class CheckoutYBusquedaTest extends TestCase
{
    use RefreshDatabase;

    private function presentacion(array $pres = [], array $prod = []): Presentacion
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

    // --- #18: checkout con productos caídos --------------------------------

    /** Si todo el carrito se apagó, no se crea un pedido vacío de $0. */
    public function test_no_crea_un_pedido_vacio_si_el_carrito_se_cayo(): void
    {
        $p = $this->presentacion();
        $cliente = User::factory()->create(['role' => 'cliente']);

        $p->update(['activo' => false]);  // se apaga después de armar el carrito

        $this->actingAs($cliente)
            ->withSession(['cart' => [(string) $p->id => 2]])
            ->post('/checkout', ['entrega' => 'retiro'])
            ->assertRedirect(route('cart.index'));

        $this->assertSame(0, Pedido::count(), 'Se creó un pedido fantasma.');
    }

    /** Si sólo una parte se cayó, tampoco cobra de menos en silencio. */
    public function test_no_cobra_de_menos_si_una_parte_del_carrito_se_cayo(): void
    {
        $vivo = $this->presentacion();
        $muerto = $this->presentacion();
        $cliente = User::factory()->create(['role' => 'cliente']);

        $muerto->update(['activo' => false]);

        $this->actingAs($cliente)
            ->withSession(['cart' => [(string) $vivo->id => 1, (string) $muerto->id => 1]])
            ->post('/checkout', ['entrega' => 'retiro'])
            ->assertRedirect(route('cart.index'));

        $this->assertSame(0, Pedido::count());
    }

    /** El checkout normal sigue funcionando. */
    public function test_un_checkout_valido_crea_el_pedido(): void
    {
        $p = $this->presentacion();
        $cliente = User::factory()->create(['role' => 'cliente']);

        $this->actingAs($cliente)
            ->withSession(['cart' => [(string) $p->id => 2]])
            ->post('/checkout', ['entrega' => 'retiro']);

        $this->assertSame(1, Pedido::count());
        $this->assertEquals(2000, Pedido::first()->total);
    }

    /** La pantalla de checkout no se muestra vacía: manda al carrito. */
    public function test_la_pantalla_de_checkout_manda_al_carrito_si_se_cayo(): void
    {
        $p = $this->presentacion();
        $cliente = User::factory()->create(['role' => 'cliente']);
        $p->update(['activo' => false]);

        $this->actingAs($cliente)
            ->withSession(['cart' => [(string) $p->id => 1]])
            ->get('/checkout')
            ->assertRedirect(route('cart.index'));
    }

    // --- #30: búsqueda con arreglo -----------------------------------------

    public function test_buscar_con_un_arreglo_no_tira_500(): void
    {
        $this->get('/productos?buscar[]=x')->assertRedirect(route('productos.index'));
    }

    public function test_marca_con_un_arreglo_no_tira_500(): void
    {
        $this->get('/productos?marca[]=1')->assertRedirect(route('productos.index'));
    }

    public function test_el_autocompletado_con_arreglo_no_tira_500(): void
    {
        $this->getJson('/api/buscar?q[]=x')->assertOk()->assertExactJson([]);
    }

    public function test_una_busqueda_normal_sigue_andando(): void
    {
        $this->presentacion(prod: ['nombre' => 'Tofu ahumado']);

        $this->get('/productos?buscar=tofu')->assertOk();
    }
}
