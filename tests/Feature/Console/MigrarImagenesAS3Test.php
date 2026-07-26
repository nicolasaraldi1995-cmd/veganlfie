<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrarImagenesAS3Test extends TestCase
{
    public function test_copia_todos_los_archivos_del_disco_origen_al_destino(): void
    {
        Storage::fake('origen');
        Storage::fake('destino');

        Storage::disk('origen')->put('productos/uno.jpg', 'contenido-uno');
        Storage::disk('origen')->put('marcas/logo.png', 'contenido-logo');

        $this->artisan('imagenes:migrar-a-s3', ['--origen' => 'origen', '--destino' => 'destino'])
            ->assertSuccessful();

        Storage::disk('destino')->assertExists('productos/uno.jpg');
        Storage::disk('destino')->assertExists('marcas/logo.png');
        $this->assertEquals('contenido-uno', Storage::disk('destino')->get('productos/uno.jpg'));
    }

    public function test_no_falla_si_el_disco_origen_esta_vacio(): void
    {
        Storage::fake('origen');
        Storage::fake('destino');

        $this->artisan('imagenes:migrar-a-s3', ['--origen' => 'origen', '--destino' => 'destino'])
            ->assertSuccessful();
    }
}
