<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrarImagenesAS3Test extends TestCase
{
    public function test_copia_las_imagenes_publicas_al_destino(): void
    {
        // El comando sólo migra desde 'public' (las imágenes); el disco privado
        // con los costos no se toca (ver #16 de la auditoría).
        Storage::fake('public');
        Storage::fake('destino');

        Storage::disk('public')->put('productos/uno.jpg', 'contenido-uno');
        Storage::disk('public')->put('marcas/logo.png', 'contenido-logo');

        $this->artisan('imagenes:migrar-a-s3', ['--origen' => 'public', '--destino' => 'destino'])
            ->assertSuccessful();

        Storage::disk('destino')->assertExists('productos/uno.jpg');
        Storage::disk('destino')->assertExists('marcas/logo.png');
        $this->assertEquals('contenido-uno', Storage::disk('destino')->get('productos/uno.jpg'));
    }

    public function test_no_falla_si_el_disco_publico_esta_vacio(): void
    {
        Storage::fake('public');
        Storage::fake('destino');

        $this->artisan('imagenes:migrar-a-s3', ['--origen' => 'public', '--destino' => 'destino'])
            ->assertSuccessful();
    }

    public function test_no_migra_desde_el_disco_privado(): void
    {
        $this->artisan('imagenes:migrar-a-s3', ['--origen' => 'local', '--destino' => 'destino'])
            ->assertFailed();
    }
}
