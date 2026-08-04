<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Importador;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El importador leía el archivo del disco "local" a secas, pero el formulario
 * lo guarda en el disco del panel, que en producción es un bucket. Ahí no hay
 * ruta local y el importador fallaba.
 */
class ImportadorTest extends TestCase
{
    use RefreshDatabase;

    public function test_lee_los_encabezados_del_disco_configurado(): void
    {
        // Un disco distinto de "local", como en producción.
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');

        $csv = "basura\nbasura\nbasura\nbasura\nNombre,Marca,Categoria,Unidad,Precio\nQueso,Casa Vegana,Quesos,200gr,1000\n";
        Storage::disk('public')->put('imports/lista.csv', $csv);

        $componente = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(Importador::class)
            ->fillForm([
                // Filament guarda el archivo con una clave por adjunto.
                'archivo' => ['adjunto-1' => 'imports/lista.csv'],
                'header_row' => 5,
            ])
            ->call('loadHeaders');

        $this->assertSame('map', $componente->get('step'), 'No pasó al paso de mapear: no pudo leer el archivo.');
        $this->assertContains('Nombre', $componente->get('headers'));
        $this->assertContains('Marca', $componente->get('headers'));
    }
}
