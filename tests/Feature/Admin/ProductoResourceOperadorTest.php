<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ProductoResource\Pages\CreateProducto;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El campo precio de la presentación está oculto para operador (isAdmin() only).
 * Sin dehydratedWhenHidden(), un operador que crea un producto nuevo manda
 * precio=null al INSERT (Filament descarta el estado de un campo oculto al
 * dehidratar), lo que rompe el NOT NULL de la columna *después* de que el
 * Producto ya se guardó, dejando un producto huérfano sin presentaciones.
 *
 * default(0) es lo que un fillForm()/mount() real deja precargado en el campo
 * oculto (fillForm() en el test no reevalúa defaults para claves ausentes, así
 * que se pasa 0 explícito para representar ese mismo estado).
 */
class ProductoResourceOperadorTest extends TestCase
{
    use RefreshDatabase;

    public function test_operador_puede_crear_un_producto_con_presentacion_sin_ver_el_precio(): void
    {
        $operador = User::factory()->create(['role' => 'operador']);
        $marca = Marca::factory()->create();
        $categoria = Categoria::factory()->create();

        Livewire::actingAs($operador)
            ->test(CreateProducto::class)
            ->fillForm([
                'nombre' => 'Producto de prueba',
                'marca_id' => $marca->id,
                'categoria_id' => $categoria->id,
                'presentaciones' => [
                    ['unidad' => '500gr', 'precio' => 0, 'stock' => 10, 'activo' => true],
                ],
            ])
            ->call('create')
            ->assertHasNoErrors();

        $producto = Producto::where('nombre', 'Producto de prueba')->firstOrFail();
        $presentacion = Presentacion::where('producto_id', $producto->id)->first();

        $this->assertNotNull($presentacion, 'la presentación no debería quedar huérfana sin guardarse');
        $this->assertEquals(0, $presentacion->precio);
        $this->assertEquals(10, $presentacion->stock);
    }

    public function test_si_falla_la_creacion_de_la_presentacion_no_queda_un_producto_huerfano(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $marca = Marca::factory()->create();
        $categoria = Categoria::factory()->create();

        // Un SKU duplicado pasa la validación de Filament (el campo no tiene regla
        // ->unique()) pero choca con el índice único de presentaciones.sku recién
        // al ejecutar el INSERT — es decir, después de que, sin
        // databaseTransactions() en el panel, el Producto ya se habría guardado.
        $otroProducto = Producto::factory()->create();
        Presentacion::factory()->create(['producto_id' => $otroProducto->id, 'sku' => 'DUP-SKU']);

        $threw = false;
        try {
            Livewire::actingAs($admin)
                ->test(CreateProducto::class)
                ->fillForm([
                    'nombre' => 'Producto que no debe quedar huérfano',
                    'marca_id' => $marca->id,
                    'categoria_id' => $categoria->id,
                    'presentaciones' => [
                        ['unidad' => '500gr', 'sku' => 'DUP-SKU', 'precio' => 100, 'stock' => 10, 'activo' => true],
                    ],
                ])
                ->call('create');
        } catch (\Throwable $e) {
            $threw = true;
        }

        $this->assertTrue($threw, 'se esperaba que el SKU duplicado fallara a nivel de base de datos');
        $this->assertEquals(0, Producto::where('nombre', 'Producto que no debe quedar huérfano')->count());
    }
}
