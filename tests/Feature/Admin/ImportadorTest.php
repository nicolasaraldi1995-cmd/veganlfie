<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Importador;
use App\Models\User;
use Filament\Forms\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La lista del proveedor trae los costos y los márgenes. Iba al disco del
 * panel, que se publica en public/storage: se bajaba entera desde
 * /storage/imports/ sin tener cuenta, porque el servidor web la sirve antes de
 * llegar a PHP. Ahora va al disco privado, que queda fuera de ese árbol.
 */
class ImportadorTest extends TestCase
{
    use RefreshDatabase;

    private const LISTA = "basura\nbasura\nbasura\nbasura\nNombre,Marca,Categoria,Unidad,Precio\nQueso,Casa Vegana,Quesos,200gr,1000\n";

    public function test_lee_el_archivo_del_disco_privado(): void
    {
        // El disco del panel, distinto del privado, como en producción.
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');
        Storage::fake('local');

        Storage::disk('local')->put('imports/lista.csv', self::LISTA);

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

    /**
     * Lo que importa de verdad: que el formulario no lo deje en la carpeta que
     * publica el servidor web.
     */
    public function test_el_formulario_guarda_fuera_de_la_carpeta_publicada(): void
    {
        config(['filament.default_filesystem_disk' => 'public']);

        $campo = collect(
            app(Importador::class)->form(new Form(
                Livewire::actingAs(User::factory()->create(['role' => 'admin']))
                    ->test(Importador::class)
                    ->instance()
            ))->getComponents()
        )->firstWhere(fn ($componente) => $componente->getName() === 'archivo');

        $this->assertNotNull($campo);
        $this->assertSame(
            'local',
            $campo->getDiskName(),
            'El disco del panel se symlinkea a public/storage: ahí la lista de costos queda al alcance de cualquiera.'
        );
    }
}
