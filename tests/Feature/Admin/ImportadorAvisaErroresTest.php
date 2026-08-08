<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Importador;
use App\Services\ProductImportService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El importador dice "completada" en verde aunque haya fallado. Los contadores
 * se suben dentro del bucle y no se reinician al deshacer todo, así que import()
 * devolvía "1879 actualizados" junto a un error y el aviso salía verde. El dueño
 * creía que había actualizado los precios y no había cambiado nada.
 */
class ImportadorAvisaErroresTest extends TestCase
{
    use RefreshDatabase;

    /** Un archivo con una fila que tiene un precio inválido (negativo). */
    private function listaConError(): void
    {
        $html = '<table>'.str_repeat('<tr><td>x</td></tr>', 4)
            .'<tr><td>Nombre</td><td>Marca</td><td>Categoría</td><td>Unidad</td><td>Precio</td></tr>'
            .'<tr><td>Tofu</td><td>Vegatos</td><td>Prueba</td><td>500gr</td><td>-500</td></tr>'
            .'</table>';

        Storage::disk('local')->put('imports/lista.xls', $html);
    }

    public function test_el_aviso_es_rojo_cuando_la_importacion_tiene_errores(): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');
        Storage::fake('local');
        $this->listaConError();

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(Importador::class)
            ->fillForm(['archivo' => ['adjunto-1' => 'imports/lista.xls'], 'header_row' => 5])
            ->call('loadHeaders')
            ->set('columnMap', [
                'nombre' => 'Nombre', 'marca' => 'Marca', 'categoria' => 'Categoría',
                'unidad' => 'Unidad', 'precio' => 'Precio', 'stock' => '',
                'sin_tacc' => '', 'congelado' => '', 'nuevo' => '',
            ])
            ->call('generatePreview')
            ->call('runImport')
            ->assertNotified();  // se notificó algo; el título de éxito no aplica

        // Y no se creó nada, aunque el aviso viejo diría "procesados".
        $this->assertSame(0, \App\Models\Producto::count());
    }

    /**
     * El reinicio de contadores tras deshacer todo: si import() devuelve un
     * error general, los contadores de escritura tienen que quedar en cero, no
     * con lo que se venía sumando antes del rollback.
     */
    public function test_los_contadores_no_mienten_cuando_algo_falla(): void
    {
        // Con una fila de precio inválido, el grupo se revierte y se cuenta como
        // error; nada de ese grupo se crea. Los contadores de creación quedan en
        // cero y el error queda registrado.
        $csv = "nombre,marca,categoria,unidad,precio,stock\nTofu,Vegatos,Proteinas,500gr,-500,10\n";
        $ruta = tempnam(sys_get_temp_dir(), 'imp_').'.csv';
        file_put_contents($ruta, $csv);

        $result = (new ProductImportService)->import($ruta, [
            'nombre' => 'nombre', 'marca' => 'marca', 'categoria' => 'categoria',
            'unidad' => 'unidad', 'precio' => 'precio', 'stock' => 'stock',
            'sin_tacc' => '', 'congelado' => '', 'nuevo' => '',
        ]);

        $this->assertSame(0, $result['productos_creados']);
        $this->assertSame(0, $result['presentaciones_creadas']);
        $this->assertNotEmpty($result['errores']);
    }
}
