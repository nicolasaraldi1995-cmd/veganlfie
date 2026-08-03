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
 * Los precios viajan al navegador dentro de los props de Inertia, así que
 * esconderlos solo en el diseño no alcanza: cualquiera los leería desde las
 * herramientas del navegador. Estos tests verifican el corte real, en el
 * servidor.
 */
class PreciosOcultosParaInvitadosTest extends TestCase
{
    use RefreshDatabase;

    private function crearProducto(): Producto
    {
        $producto = Producto::factory()->create([
            'marca_id' => Marca::factory()->create()->id,
            'categoria_id' => Categoria::factory()->create()->id,
        ]);

        Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'precio' => 12345,
            'precio_costo' => 6789,
            'margen_porcentaje' => 40,
            'descuento_porcentaje' => 15,
            'stock' => 10,
            'activo' => true,
        ]);

        return $producto;
    }

    public function test_un_invitado_no_recibe_el_precio_del_producto(): void
    {
        $this->crearProducto();

        $response = $this->get('/productos');

        $response->assertOk();
        $response->assertDontSee('12345');
    }

    public function test_un_invitado_nunca_recibe_costo_ni_margen(): void
    {
        $this->crearProducto();

        $response = $this->get('/productos');

        $response->assertOk();
        // Los valores internos del negocio no pueden salir jamás al sitio público.
        $response->assertDontSee('6789');
        $response->assertDontSee('precio_costo');
        $response->assertDontSee('margen_porcentaje');
    }

    public function test_un_cliente_registrado_si_ve_el_precio(): void
    {
        $this->crearProducto();
        $cliente = User::factory()->create(['role' => 'cliente']);

        $response = $this->actingAs($cliente)->get('/productos');

        $response->assertOk();
        $response->assertSee('12345');
    }

    public function test_un_cliente_registrado_no_ve_costo_ni_margen(): void
    {
        $this->crearProducto();
        $cliente = User::factory()->create(['role' => 'cliente']);

        $response = $this->actingAs($cliente)->get('/productos');

        $response->assertOk();
        $response->assertDontSee('precio_costo');
        $response->assertDontSee('margen_porcentaje');
    }

    public function test_el_admin_conserva_costo_y_margen(): void
    {
        $producto = $this->crearProducto();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin);
        $data = $producto->presentaciones()->first()->toArray();

        // Si esto se recortara, el formulario del panel admin perdería los
        // valores de costo/margen al editar un producto.
        $this->assertArrayHasKey('precio_costo', $data);
        $this->assertArrayHasKey('margen_porcentaje', $data);
        $this->assertArrayHasKey('precio', $data);
    }
}
