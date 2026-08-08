<?php

namespace Tests\Feature\Admin;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cambiar el margen o el descuento de una marca rehace el precio de venta de
 * todos sus productos.
 *
 * Sin esto el porcentaje se veía en cada producto pero no gobernaba nada: se
 * podía poner 100% de margen y el precio quedaba donde estaba. Es el mismo
 * criterio que ya tenía el IVA por marca.
 */
class PreciosPorMarcaTest extends TestCase
{
    use RefreshDatabase;

    private Marca $marca;

    protected function setUp(): void
    {
        parent::setUp();

        $this->marca = Marca::factory()->create([
            'nombre' => 'Célula Cocina',
            'margen_porcentaje' => 30,
            'descuento_porcentaje' => null,
            'activo' => true,
        ]);
    }

    private function presentacion(array $atributos = []): Presentacion
    {
        $producto = Producto::factory()->create([
            'marca_id' => $this->marca->id,
            'categoria_id' => Categoria::factory()->create()->id,
            'activo' => true,
        ]);

        return Presentacion::factory()->create(array_merge([
            'producto_id' => $producto->id,
            'unidad' => '120gr',
            'precio' => 4404.40,     // 2800 × 1,30 × 1,21
            'precio_costo' => 2800,
            'margen_porcentaje' => null,
            'descuento_porcentaje' => null,
            'iva' => true,
            'activo' => true,
        ], $atributos));
    }

    /** El caso del video: la marca pasa a 100% y el precio tiene que moverse. */
    public function test_subir_el_margen_de_la_marca_mueve_el_precio(): void
    {
        $p = $this->presentacion();

        $this->marca->update(['margen_porcentaje' => 100]);

        // 2800 × 2,00 × 1,21 = 6776
        $this->assertEquals(6776, $p->fresh()->precio);
    }

    public function test_bajar_el_margen_tambien(): void
    {
        $p = $this->presentacion();

        $this->marca->update(['margen_porcentaje' => 20]);

        // 2800 × 1,20 × 1,21 = 4065,60
        $this->assertEquals(4065.60, $p->fresh()->precio);
    }

    public function test_el_descuento_del_proveedor_tambien_mueve_el_precio(): void
    {
        $p = $this->presentacion();

        $this->marca->update(['descuento_porcentaje' => 10]);

        // 2800 −10% = 2520, ×1,30 = 3276, ×1,21 = 3963,96
        $this->assertEquals(3963.96, $p->fresh()->precio);
    }

    /** Mueve a todos sus productos de una, que es de lo que se trata. */
    public function test_mueve_a_todos_los_productos_de_la_marca(): void
    {
        $unos = collect([
            $this->presentacion(['precio_costo' => 2800]),
            $this->presentacion(['precio_costo' => 4501]),
            $this->presentacion(['precio_costo' => 4180]),
        ]);

        $this->marca->update(['margen_porcentaje' => 100]);

        $this->assertEquals(6776.00, $unos[0]->fresh()->precio);
        $this->assertEquals(10892.42, $unos[1]->fresh()->precio);
        $this->assertEquals(10115.60, $unos[2]->fresh()->precio);
    }

    /** El que tiene su propio margen no se deja gobernar por la marca. */
    public function test_el_que_tiene_margen_propio_no_se_mueve(): void
    {
        $p = $this->presentacion(['margen_porcentaje' => 50, 'precio' => 5082]);

        $this->marca->update(['margen_porcentaje' => 100]);

        // 2800 × 1,50 × 1,21 = 5082: sigue con el suyo
        $this->assertEquals(5082, $p->fresh()->precio);
    }

    /** Sin costo no hay cuenta que hacer: el precio de la lista se respeta. */
    public function test_sin_costo_cargado_el_precio_no_se_toca(): void
    {
        $p = $this->presentacion(['precio_costo' => null, 'precio' => 9999]);

        $this->marca->update(['margen_porcentaje' => 100]);

        $this->assertEquals(9999, $p->fresh()->precio, 'Pisó un precio que no podía calcular.');
    }

    /** Tocar la marca sin cambiar los porcentajes no mueve nada. */
    public function test_cambiar_el_nombre_de_la_marca_no_toca_precios(): void
    {
        $p = $this->presentacion();

        $this->marca->update(['nombre' => 'Célula Cocina SRL']);

        $this->assertEquals(4404.40, $p->fresh()->precio);
    }

    /** Y no se mete con los productos de otra marca. */
    public function test_no_toca_los_productos_de_otras_marcas(): void
    {
        $otra = Marca::factory()->create(['margen_porcentaje' => 30, 'activo' => true]);
        $ajeno = Presentacion::factory()->create([
            'producto_id' => Producto::factory()->create([
                'marca_id' => $otra->id,
                'categoria_id' => Categoria::factory()->create()->id,
            ])->id,
            'precio' => 4404.40,
            'precio_costo' => 2800,
            'iva' => true,
            'activo' => true,
        ]);

        $this->marca->update(['margen_porcentaje' => 100]);

        $this->assertEquals(4404.40, $ajeno->fresh()->precio);
    }

    /** La oferta por porcentaje se rehace sobre el precio nuevo. */
    public function test_la_oferta_se_recalcula_sobre_el_precio_nuevo(): void
    {
        $p = $this->presentacion(['oferta_porcentaje' => 20, 'oferta_precio' => 3523.52]);

        $this->marca->update(['margen_porcentaje' => 100]);

        // Precio nuevo 6776, menos 20% = 5420,80
        $this->assertEquals(5420.80, $p->fresh()->oferta_precio);
    }

    /** Cuántos tocó y cuántos no pudo: es lo que se le avisa al dueño. */
    public function test_informa_cuantos_movio_y_cuantos_no_pudo(): void
    {
        $this->presentacion(['precio_costo' => 2800]);
        $this->presentacion(['precio_costo' => null]);
        $this->presentacion(['precio_costo' => null]);

        $stats = app(\App\Services\PreciosPorMarca::class)->aplicar($this->marca->fresh());

        $this->assertSame(2, $stats['sinCosto']);
        $this->assertSame(0, $stats['tocadas'], 'Sin cambiar el porcentaje no hay nada que mover.');
    }
}
