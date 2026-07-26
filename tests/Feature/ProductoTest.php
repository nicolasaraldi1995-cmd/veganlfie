<?php

namespace Tests\Feature;

use App\Models\Presentacion;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductoTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_producto_soft_deletes_its_presentaciones(): void
    {
        $producto = Producto::factory()->create();
        $presentacion = Presentacion::factory()->for($producto)->create();

        $producto->delete();

        $this->assertSoftDeleted($presentacion);
    }

    public function test_restoring_producto_restores_its_presentaciones(): void
    {
        $producto = Producto::factory()->create();
        $presentacion = Presentacion::factory()->for($producto)->create();

        $producto->delete();
        $producto->restore();

        $this->assertNotSoftDeleted($presentacion->fresh());
    }

    public function test_dos_productos_con_el_mismo_nombre_reciben_slugs_distintos(): void
    {
        $uno = Producto::factory()->create(['nombre' => 'Nuggets sabor pollo']);
        $dos = Producto::factory()->create(['nombre' => 'Nuggets sabor pollo']);
        $tres = Producto::factory()->create(['nombre' => 'Nuggets sabor pollo']);

        $this->assertEquals('nuggets-sabor-pollo', $uno->slug);
        $this->assertEquals('nuggets-sabor-pollo-2', $dos->slug);
        $this->assertEquals('nuggets-sabor-pollo-3', $tres->slug);
    }

    public function test_el_slug_de_un_producto_borrado_sigue_bloqueando_el_slug_para_uno_nuevo(): void
    {
        $borrado = Producto::factory()->create(['nombre' => 'Producto viejo']);
        $borrado->delete();

        $nuevo = Producto::factory()->create(['nombre' => 'Producto viejo']);

        $this->assertNotEquals($borrado->slug, $nuevo->slug);
        $this->assertEquals('producto-viejo-2', $nuevo->slug);
    }
}
