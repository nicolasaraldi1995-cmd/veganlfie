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
}
