<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Apagar un producto lo sacaba de los listados y de nada más.
 *
 * La ficha seguía respondiendo por el link directo, con precio y con el botón
 * de comprar; el carrito lo aceptaba y el checkout lo cobraba. En la base real
 * eran 331 productos apagados con 344 presentaciones vivas y con precio.
 *
 * La causa era que "se puede vender" se decidía mirando la presentación y nada
 * más: Presentacion::activos() no le preguntaba nunca al producto.
 */
class DadoDeBajaNoSeVendeTest extends TestCase
{
    use RefreshDatabase;

    private function producto(array $atributos = []): Producto
    {
        return Producto::factory()->create(array_merge([
            'nombre' => 'Gelatina vegana sabor frutilla',
            'slug' => 'gelatina-vegana-sabor-frutilla',
            'marca_id' => Marca::factory()->create(['activo' => true])->id,
            'categoria_id' => Categoria::factory()->create(['activo' => true])->id,
            'activo' => true,
        ], $atributos));
    }

    private function presentacion(Producto $producto): Presentacion
    {
        return Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'unidad' => '100gr',
            'precio' => 229.50,
            'stock' => 10,
            'activo' => true,
        ]);
    }

    // --- La ficha del producto -------------------------------------------

    public function test_la_ficha_de_un_producto_de_baja_no_existe(): void
    {
        $producto = $this->producto(['activo' => false]);
        $this->presentacion($producto);

        $this->get('/productos/gelatina-vegana-sabor-frutilla')->assertNotFound();
    }

    public function test_la_ficha_de_un_producto_publicado_sigue_abriendo(): void
    {
        $producto = $this->producto();
        $this->presentacion($producto);

        $this->get('/productos/gelatina-vegana-sabor-frutilla')->assertOk();
    }

    // --- El carrito y el checkout ----------------------------------------

    /** El agujero de fondo: el carrito miraba la presentación y no el producto. */
    public function test_el_carrito_no_resuelve_la_presentacion_de_un_producto_de_baja(): void
    {
        $producto = $this->producto(['activo' => false]);
        $presentacion = $this->presentacion($producto);

        $items = app(CartService::class)->resolveItems([(string) $presentacion->id => 2]);

        $this->assertSame([], $items, 'El carrito aceptó un producto dado de baja.');
        $this->assertSame(0.0, app(CartService::class)->total([(string) $presentacion->id => 2]));
    }

    public function test_el_carrito_sigue_resolviendo_lo_que_esta_publicado(): void
    {
        $presentacion = $this->presentacion($this->producto());

        // Con cuenta: al invitado el carrito le esconde los precios a propósito.
        $this->actingAs(User::factory()->create(['role' => 'cliente']));

        $items = app(CartService::class)->resolveItems([(string) $presentacion->id => 2]);

        $this->assertCount(1, $items);
        $this->assertEquals(229.50, $items[0]['precio']);
    }

    public function test_agregar_al_carrito_un_producto_de_baja_lo_rechaza(): void
    {
        $producto = $this->producto(['activo' => false]);
        $presentacion = $this->presentacion($producto);

        $this->actingAs(User::factory()->create(['role' => 'cliente']))
            ->post('/carrito/add', ['presentacion_id' => $presentacion->id, 'cantidad' => 1]);

        $this->assertSame([], app(CartService::class)->resolveItems(session('cart', [])));
    }

    // --- Marca y categoría ------------------------------------------------

    public function test_la_pagina_de_una_marca_de_baja_no_existe(): void
    {
        $marca = Marca::factory()->create(['nombre' => 'Asana', 'slug' => 'asana', 'activo' => false]);

        $this->get('/marcas/asana')->assertNotFound();
    }

    public function test_la_pagina_de_una_categoria_de_baja_no_existe(): void
    {
        Categoria::factory()->create(['nombre' => 'Discontinuados', 'slug' => 'discontinuados', 'activo' => false]);

        $this->get('/categorias/discontinuados')->assertNotFound();
    }

    public function test_las_paginas_publicadas_siguen_abriendo(): void
    {
        $marca = Marca::factory()->create(['slug' => 'felices-las-vacas', 'activo' => true]);
        $categoria = Categoria::factory()->create(['slug' => 'quesos', 'activo' => true]);

        $this->get('/marcas/felices-las-vacas')->assertOk();
        $this->get('/categorias/quesos')->assertOk();
    }

    // --- Y lo que cuelga de la misma raíz ---------------------------------

    /**
     * El recuadro "Sin stock" del escritorio contaba presentaciones de
     * productos que ya no se venden.
     */
    public function test_el_conteo_de_sin_stock_no_cuenta_productos_de_baja(): void
    {
        $publicado = $this->producto(['slug' => 'publicado']);
        Presentacion::factory()->create(['producto_id' => $publicado->id, 'precio' => 100, 'stock' => 0, 'activo' => true]);

        $deBaja = $this->producto(['slug' => 'de-baja', 'activo' => false]);
        Presentacion::factory()->create(['producto_id' => $deBaja->id, 'precio' => 100, 'stock' => 0, 'activo' => true]);

        $this->assertSame(1, Presentacion::where('stock', '<=', 0)->activos()->count());
    }

    /** Un producto borrado del todo tampoco deja nada vendible colgando. */
    public function test_un_producto_borrado_no_deja_presentaciones_vendibles(): void
    {
        $producto = $this->producto();
        $presentacion = $this->presentacion($producto);

        $producto->delete();

        $this->assertSame(0, Presentacion::activos()->where('id', $presentacion->id)->count());
        $this->assertSame([], app(CartService::class)->resolveItems([(string) $presentacion->id => 1]));
    }

    /** Y cuando el producto vuelve, vuelve lo que colgaba de él. */
    public function test_al_reactivar_el_producto_vuelven_sus_presentaciones(): void
    {
        $producto = $this->producto(['activo' => false]);
        $presentacion = $this->presentacion($producto);

        $this->assertSame(0, Presentacion::activos()->where('id', $presentacion->id)->count());

        $producto->update(['activo' => true]);

        $this->assertSame(1, Presentacion::activos()->where('id', $presentacion->id)->count());
        $this->assertTrue($presentacion->fresh()->activo, 'Se le apagó la presentación al apagar el producto.');
    }
}
