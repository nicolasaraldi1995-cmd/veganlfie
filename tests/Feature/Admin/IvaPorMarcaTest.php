<?php

namespace Tests\Feature\Admin;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IvaPorMarcaTest extends TestCase
{
    use RefreshDatabase;

    private function marcaConPresentacion(array $datosPresentacion = []): array
    {
        $marca = Marca::create(['nombre' => 'Prueba IVA', 'activo' => true]);
        $categoria = Categoria::firstOrCreate(['nombre' => 'Prueba']);
        $producto = Producto::create(['nombre' => 'Producto IVA', 'marca_id' => $marca->id, 'categoria_id' => $categoria->id, 'activo' => true]);

        $presentacion = Presentacion::create(array_merge([
            'producto_id' => $producto->id,
            'unidad' => '500gr',
            'precio' => 1000,
            'stock' => 10,
            'activo' => true,
        ], $datosPresentacion));

        return [$marca, $presentacion];
    }

    public function test_prender_el_iva_sube_el_21_por_ciento(): void
    {
        [$marca, $presentacion] = $this->marcaConPresentacion();

        $marca->update(['iva' => true]);

        $this->assertEquals(1210.00, $presentacion->fresh()->precio);
        $this->assertTrue($presentacion->fresh()->iva);
    }

    public function test_apagarlo_devuelve_el_precio_anterior(): void
    {
        [$marca, $presentacion] = $this->marcaConPresentacion();

        $marca->update(['iva' => true]);
        $marca->update(['iva' => false]);

        $this->assertEquals(1000.00, $presentacion->fresh()->precio);
        $this->assertFalse($presentacion->fresh()->iva);
    }

    /**
     * Guardar la marca de nuevo con el IVA ya prendido no puede volver a subir
     * los precios: sería un 21% arriba de otro 21%.
     */
    public function test_prenderlo_dos_veces_no_lo_suma_dos_veces(): void
    {
        [$marca, $presentacion] = $this->marcaConPresentacion();

        $marca->update(['iva' => true]);
        $marca->update(['iva' => true]);
        $marca->fresh()->update(['nombre' => 'Otro nombre']);

        $this->assertEquals(1210.00, $presentacion->fresh()->precio);
    }

    /**
     * Con costo y margen cargados se rehace el cálculo completo en vez de
     * multiplicar el precio de venta.
     */
    public function test_con_costo_y_margen_recalcula_desde_el_costo(): void
    {
        [$marca, $presentacion] = $this->marcaConPresentacion([
            'precio' => 999,          // un precio viejo, que se descarta
            'precio_costo' => 1000,
            'descuento_porcentaje' => 10,
            'margen_porcentaje' => 50,
        ]);

        $marca->update(['iva' => true]);

        // 1000 - 10% = 900 ; +50% = 1350 ; +21% = 1633.50
        $this->assertEquals(1633.50, $presentacion->fresh()->precio);

        $marca->update(['iva' => false]);

        $this->assertEquals(1350.00, $presentacion->fresh()->precio);
    }

    public function test_la_oferta_por_porcentaje_se_recalcula_sobre_el_precio_nuevo(): void
    {
        [$marca, $presentacion] = $this->marcaConPresentacion([
            'oferta_porcentaje' => 10,
            'oferta_precio' => 900,
        ]);

        $marca->update(['iva' => true]);

        // Precio 1210, menos 10% de oferta.
        $this->assertEquals(1089.00, $presentacion->fresh()->oferta_precio);
    }

    public function test_no_toca_los_productos_de_otras_marcas(): void
    {
        [$marca] = $this->marcaConPresentacion();

        $otra = Marca::create(['nombre' => 'Otra marca', 'activo' => true]);
        $productoAjeno = Producto::create(['nombre' => 'Ajeno', 'marca_id' => $otra->id, 'categoria_id' => Categoria::firstOrCreate(['nombre' => 'Prueba'])->id, 'activo' => true]);
        $ajena = Presentacion::create([
            'producto_id' => $productoAjeno->id,
            'unidad' => '1u',
            'precio' => 500,
            'stock' => 1,
            'activo' => true,
        ]);

        $marca->update(['iva' => true]);

        $this->assertEquals(500.00, $ajena->fresh()->precio);
    }
}
