<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\PrecioResource\Pages\ListPrecios;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pantalla donde el dueño ve costo, descuento, margen y precio de venta de
 * todo el catálogo en una línea por presentación.
 */
class PantallaDePreciosTest extends TestCase
{
    use RefreshDatabase;

    private function presentacion(array $atributos = []): Presentacion
    {
        $producto = Producto::factory()->create([
            'nombre' => 'Mozzarella vegana',
            'marca_id' => Marca::factory()->create(['nombre' => 'Felices las Vacas'])->id,
            'categoria_id' => Categoria::factory()->create()->id,
        ]);

        return Presentacion::factory()->create(array_merge([
            'producto_id' => $producto->id,
            'unidad' => '1u',
            'precio' => 1500,
            'precio_costo' => 1000,
            'margen_porcentaje' => 50,
            'descuento_porcentaje' => null,
            'iva' => false,
            'activo' => true,
        ], $atributos));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** El costo es el único dato que el panel le esconde al operador de punta a punta. */
    public function test_el_operador_no_entra(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'operador']))
            ->test(ListPrecios::class)
            ->assertForbidden();
    }

    public function test_el_admin_ve_los_cuatro_numeros_en_la_misma_fila(): void
    {
        $presentacion = $this->presentacion();

        Livewire::actingAs($this->admin())
            ->test(ListPrecios::class)
            ->assertCanSeeTableRecords([$presentacion])
            ->assertTableColumnStateSet('precio_costo', '1000.00', $presentacion)
            ->assertTableColumnStateSet('margen_porcentaje', '50.00', $presentacion)
            ->assertTableColumnStateSet('precio', '1500.00', $presentacion);
    }

    /** Se escribe el costo nuevo en la fila y el precio de venta sale solo. */
    public function test_cargar_el_costo_rehace_el_precio_de_venta(): void
    {
        $presentacion = $this->presentacion();

        Livewire::actingAs($this->admin())
            ->test(ListPrecios::class)
            ->call('updateTableColumnState', 'precio_costo', (string) $presentacion->id, '2000');

        $presentacion->refresh();

        $this->assertEquals(2000, $presentacion->precio_costo);
        $this->assertEquals(3000, $presentacion->precio, 'El precio de venta no siguió al costo.');
    }

    public function test_el_descuento_del_proveedor_baja_el_precio(): void
    {
        $presentacion = $this->presentacion();

        Livewire::actingAs($this->admin())
            ->test(ListPrecios::class)
            ->call('updateTableColumnState', 'descuento_porcentaje', (string) $presentacion->id, '10');

        // 1000 menos 10% = 900, más 50% de margen = 1350
        $this->assertEquals(1350, $presentacion->fresh()->precio);
    }

    /** Marca entera: se filtra por la marca, se marcan todas y se aplica el %. */
    public function test_el_aumento_masivo_mueve_costo_y_precio_juntos(): void
    {
        $presentacion = $this->presentacion();

        Livewire::actingAs($this->admin())
            ->test(ListPrecios::class)
            ->callTableBulkAction('aumentar', [$presentacion], ['porcentaje' => 10]);

        $presentacion->refresh();

        $this->assertEquals(1100, $presentacion->precio_costo);
        $this->assertEquals(1650, $presentacion->precio, '1100 de costo con 50% de margen son 1650.');
    }

    /**
     * Hoy no hay un solo costo cargado en las 2161 presentaciones. Mientras eso
     * siga así el aumento tiene que mover el precio de venta igual, o la
     * pantalla no sirve para nada hasta que alguien cargue el catálogo entero.
     */
    public function test_sin_costo_cargado_el_aumento_mueve_el_precio_de_venta(): void
    {
        $presentacion = $this->presentacion([
            'precio' => 1000,
            'precio_costo' => null,
            'margen_porcentaje' => null,
        ]);

        Livewire::actingAs($this->admin())
            ->test(ListPrecios::class)
            ->callTableBulkAction('aumentar', [$presentacion], ['porcentaje' => 10]);

        $presentacion->refresh();

        $this->assertEquals(1100, $presentacion->precio);
        $this->assertNull($presentacion->precio_costo, 'Se inventó un costo que nadie cargó.');
    }

    /** La oferta se calculaba sobre el precio viejo y quedaba mintiendo. */
    public function test_el_aumento_rehace_el_precio_de_oferta(): void
    {
        $presentacion = $this->presentacion(['oferta_porcentaje' => 20]);

        Livewire::actingAs($this->admin())
            ->test(ListPrecios::class)
            ->callTableBulkAction('aumentar', [$presentacion], ['porcentaje' => 10]);

        // Precio nuevo 1650, menos el 20% de oferta = 1320
        $this->assertEquals(1320, $presentacion->fresh()->oferta_precio);
    }
}
