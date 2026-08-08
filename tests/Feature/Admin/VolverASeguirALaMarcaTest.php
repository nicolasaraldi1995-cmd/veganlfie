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
 * Hasta el 07/08/2026, abrir un producto y guardarlo le grababa el porcentaje
 * de la marca como propio: quedaba con una foto del número de ese momento y
 * dejaba de seguir a la marca para siempre.
 *
 * El formulario ya no lo hace. Esto es para encontrar y limpiar los que
 * quedaron marcados.
 */
class VolverASeguirALaMarcaTest extends TestCase
{
    use RefreshDatabase;

    private Marca $marca;

    protected function setUp(): void
    {
        parent::setUp();

        $this->marca = Marca::factory()->create([
            'nombre' => 'Oralí',
            'margen_porcentaje' => 40,
            'descuento_porcentaje' => 10,
            'activo' => true,
        ]);
    }

    private function presentacion(array $atributos = []): Presentacion
    {
        return Presentacion::factory()->create(array_merge([
            'producto_id' => Producto::factory()->create([
                'marca_id' => $this->marca->id,
                'categoria_id' => Categoria::factory()->create()->id,
                'activo' => true,
            ])->id,
            'unidad' => '120gr',
            'precio' => 4404.40,
            'precio_costo' => 2800,
            'iva' => true,
            'activo' => true,
        ], $atributos));
    }

    private function pantalla()
    {
        return Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ListPrecios::class);
    }

    /** El caso real: quedó clavado en 30% y la marca ya dice 40%. */
    public function test_devuelve_el_producto_a_seguir_a_su_marca(): void
    {
        $p = $this->presentacion(['margen_porcentaje' => 30, 'descuento_porcentaje' => null]);

        $this->pantalla()->callTableBulkAction('seguir_a_la_marca', [$p]);

        $fresca = $p->fresh();

        $this->assertNull($fresca->margen_porcentaje, 'Le quedó el porcentaje propio.');
        $this->assertSame(40.0, $fresca->margenEfectivo(), 'No volvió a tomar el de la marca.');
        // 2800 −10% = 2520, ×1,40 = 3528, ×1,21 = 4268,88
        $this->assertEquals(4268.88, $fresca->precio, 'No rehizo el precio con los de la marca.');
    }

    public function test_tambien_le_saca_el_descuento_propio(): void
    {
        $p = $this->presentacion(['margen_porcentaje' => null, 'descuento_porcentaje' => 25]);

        $this->pantalla()->callTableBulkAction('seguir_a_la_marca', [$p]);

        $this->assertNull($p->fresh()->descuento_porcentaje);
        $this->assertSame(10.0, $p->fresh()->descuentoEfectivo());
    }

    /** Sin costo no hay cuenta: se le saca el propio pero no se le inventa precio. */
    public function test_sin_costo_le_saca_el_propio_y_no_toca_el_precio(): void
    {
        $p = $this->presentacion(['margen_porcentaje' => 30, 'precio_costo' => null, 'precio' => 9999]);

        $this->pantalla()->callTableBulkAction('seguir_a_la_marca', [$p]);

        $this->assertNull($p->fresh()->margen_porcentaje);
        $this->assertEquals(9999, $p->fresh()->precio);
    }

    /** El que ya seguía a la marca no se toca. */
    public function test_el_que_ya_seguia_a_la_marca_queda_igual(): void
    {
        $p = $this->presentacion(['margen_porcentaje' => null, 'descuento_porcentaje' => null]);

        $this->pantalla()->callTableBulkAction('seguir_a_la_marca', [$p]);

        $this->assertEquals(4404.40, $p->fresh()->precio);
    }

    // --- El filtro para encontrarlos --------------------------------------

    public function test_el_filtro_encuentra_los_que_tienen_porcentaje_propio(): void
    {
        $propio = $this->presentacion(['margen_porcentaje' => 30]);
        $sigue = $this->presentacion(['margen_porcentaje' => null, 'descuento_porcentaje' => null]);

        $this->pantalla()
            ->filterTable('porcentaje_propio', true)
            ->assertCanSeeTableRecords([$propio])
            ->assertCanNotSeeTableRecords([$sigue]);
    }

    public function test_el_filtro_al_reves_muestra_los_que_siguen_a_la_marca(): void
    {
        $propio = $this->presentacion(['margen_porcentaje' => 30]);
        $sigue = $this->presentacion(['margen_porcentaje' => null, 'descuento_porcentaje' => null]);

        $this->pantalla()
            ->filterTable('porcentaje_propio', false)
            ->assertCanSeeTableRecords([$sigue])
            ->assertCanNotSeeTableRecords([$propio]);
    }
}
