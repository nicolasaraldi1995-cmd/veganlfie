<?php

namespace Tests\Feature;

use App\Models\Presentacion;
use App\Models\Producto;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_items_skips_presentaciones_with_deleted_producto(): void
    {
        $producto = Producto::factory()->create();
        $presentacion = Presentacion::factory()->for($producto)->create();

        $producto->delete(); // cascadea el soft-delete a la presentacion (ver Producto::booted)

        $items = app(CartService::class)->resolveItems([(string) $presentacion->id => 2]);

        $this->assertEmpty($items);
    }

    public function test_resolve_items_falls_back_when_marca_or_categoria_is_missing(): void
    {
        $producto = Producto::factory()->create();
        $presentacion = Presentacion::factory()->for($producto)->create();

        $producto->marca->delete();
        $producto->categoria->delete();

        $items = app(CartService::class)->resolveItems([(string) $presentacion->id => 1]);

        $this->assertCount(1, $items);
        $this->assertEquals('Sin marca', $items[0]['marca']);
        $this->assertEquals('Sin categoría', $items[0]['categoria']);
    }
}
