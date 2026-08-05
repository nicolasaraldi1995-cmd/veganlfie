<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Importador;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El importador sólo agrega y actualiza: un producto que el proveedor sacó de
 * la lista quedaba publicado para siempre. Ahora la previsualización muestra
 * qué queda afuera y se puede poner el catálogo a tono en el mismo paso.
 */
class ImportadorSincronizaTest extends TestCase
{
    use RefreshDatabase;

    private function producto(string $nombre, string $marca): Producto
    {
        $producto = Producto::create([
            'nombre' => $nombre,
            'marca_id' => Marca::firstOrCreate(['nombre' => $marca])->id,
            'categoria_id' => Categoria::firstOrCreate(['nombre' => 'Prueba'])->id,
            'activo' => true,
        ]);

        Presentacion::create([
            'producto_id' => $producto->id, 'unidad' => '1u', 'precio' => 100, 'stock' => 1, 'activo' => true,
        ]);

        return $producto;
    }

    private function lista(): string
    {
        $html = '<table>'.str_repeat('<tr><td>x</td></tr>', 4)
            .'<tr><td>Nombre</td><td>Marca</td><td>Categoría</td><td>Unidad</td><td>Precio</td></tr>'
            .'<tr><td>Se queda</td><td>Marca A</td><td>Prueba</td><td>1u</td><td>250,00</td></tr>'
            .'<tr><td>Cambia de marca</td><td>Marca B</td><td>Prueba</td><td>1u</td><td>300,00</td></tr>'
            .'</table>';

        $ruta = tempnam(sys_get_temp_dir(), 'lista_').'.xls';
        file_put_contents($ruta, $html);
        // Al disco privado: el del panel se publica en public/storage.
        Storage::disk('local')->put('imports/lista.xls', $html);

        return $ruta;
    }

    private function hastaLaPrevisualizacion(): Testable
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');
        Storage::fake('local');

        $this->lista();

        return Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(Importador::class)
            ->fillForm(['archivo' => ['adjunto-1' => 'imports/lista.xls'], 'header_row' => 5])
            ->call('loadHeaders')
            ->set('columnMap', [
                'nombre' => 'Nombre', 'marca' => 'Marca', 'categoria' => 'Categoría',
                'unidad' => 'Unidad', 'precio' => 'Precio', 'stock' => '',
                'sin_tacc' => '', 'congelado' => '', 'nuevo' => '',
            ])
            ->call('generatePreview');
    }

    public function test_la_previsualizacion_avisa_que_queda_afuera(): void
    {
        $this->producto('Se queda', 'Marca A');
        $this->producto('Cambia de marca', 'Marca A');
        $this->producto('Ya no se vende', 'Marca A');

        $resumen = $this->hastaLaPrevisualizacion()->assertSet('step', 'preview')->get('resumenSync');

        $this->assertSame(1, $resumen['cambiosDeMarca']);
        $this->assertSame(1, $resumen['bajas']);
    }

    public function test_sin_tildar_la_opcion_no_da_de_baja_nada(): void
    {
        $descontinuado = $this->producto('Ya no se vende', 'Marca A');
        $this->producto('Se queda', 'Marca A');

        $this->hastaLaPrevisualizacion()->call('runImport')->assertSet('step', 'result');

        $this->assertTrue($descontinuado->fresh()->activo);
    }

    public function test_al_tildarla_mueve_de_marca_y_da_de_baja(): void
    {
        $descontinuado = $this->producto('Ya no se vende', 'Marca A');
        $cambia = $this->producto('Cambia de marca', 'Marca A');
        $queda = $this->producto('Se queda', 'Marca A');

        $componente = $this->hastaLaPrevisualizacion()
            ->set('sincronizar', true)
            ->call('runImport')
            ->assertSet('step', 'result');

        $this->assertFalse($descontinuado->fresh()->activo, 'Tendría que haberse dado de baja.');
        $this->assertSame('Marca B', $cambia->fresh()->marca->nombre);
        $this->assertTrue($queda->fresh()->activo);

        // Y el precio se actualiza igual, en la misma pasada.
        $this->assertEquals(250, $queda->presentaciones()->first()->precio);
        $this->assertEquals(300, $cambia->presentaciones()->first()->precio);

        $this->assertSame(1, $componente->get('syncResult')['bajas']);
        $this->assertSame(1, $componente->get('syncResult')['marcas']);
    }
}
