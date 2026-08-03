<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Una cuenta recién creada no puede ver precios hasta que la distribuidora la
 * da de alta: sin esto, cualquiera (incluida la competencia) se registraba y se
 * llevaba la lista de precios mayorista completa.
 */
class AprobacionClientesTest extends TestCase
{
    use RefreshDatabase;

    private function crearProducto(): void
    {
        $producto = Producto::factory()->create([
            'marca_id' => Marca::factory()->create()->id,
            'categoria_id' => Categoria::factory()->create()->id,
        ]);

        Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'precio' => 12345,
            'stock' => 10,
            'activo' => true,
        ]);
    }

    public function test_una_cuenta_nueva_queda_esperando_aprobacion(): void
    {
        $this->post('/register', [
            'name' => 'Negocio Nuevo',
            'tipo_cliente' => 'negocio',
            'email' => 'nuevo@negocio.test',
            'celular' => '2477000000',
            'direccion' => 'Calle 1',
            'ciudad' => 'Pergamino',
            'password' => 'password-larga-123',
            'password_confirmation' => 'password-larga-123',
        ]);

        $user = User::where('email', 'nuevo@negocio.test')->first();

        $this->assertNotNull($user);
        $this->assertEquals('cliente', $user->role);
        $this->assertFalse($user->aprobado, 'Una cuenta nueva no debería quedar aprobada sola.');
        $this->assertFalse($user->puedeVerPrecios());
    }

    public function test_un_cliente_sin_aprobar_no_recibe_precios(): void
    {
        $this->crearProducto();
        $cliente = User::factory()->create(['role' => 'cliente', 'aprobado' => false]);

        $response = $this->actingAs($cliente)->get('/productos');

        $response->assertOk();
        $response->assertDontSee('12345');
    }

    public function test_un_cliente_aprobado_si_recibe_precios(): void
    {
        $this->crearProducto();
        $cliente = User::factory()->create(['role' => 'cliente', 'aprobado' => true]);

        $response = $this->actingAs($cliente)->get('/productos');

        $response->assertOk();
        $response->assertSee('12345');
    }

    public function test_sin_aprobar_no_puede_entrar_a_la_lista_de_precios_ni_al_checkout(): void
    {
        $cliente = User::factory()->create(['role' => 'cliente', 'aprobado' => false]);

        // No alcanza con esconderlo en pantalla: las rutas se piden a mano.
        $this->actingAs($cliente)->get('/lista-de-precios')->assertRedirect(route('home'));
        $this->actingAs($cliente)->get('/lista-de-precios/html')->assertRedirect(route('home'));
        $this->actingAs($cliente)->get('/checkout')->assertRedirect(route('home'));
    }

    public function test_el_staff_ve_precios_aunque_no_este_marcado_como_aprobado(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'aprobado' => false]);
        $operador = User::factory()->create(['role' => 'operador', 'aprobado' => false]);

        $this->assertTrue($admin->puedeVerPrecios());
        $this->assertTrue($operador->puedeVerPrecios());
    }
}
