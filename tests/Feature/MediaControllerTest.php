<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sirve_un_archivo_existente_con_el_content_type_correcto(): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');
        Storage::disk('public')->put('productos/foto.jpg', 'contenido-de-la-foto');

        $response = $this->get('/media/productos/foto.jpg');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertEquals('contenido-de-la-foto', $response->getContent());
    }

    public function test_404_si_el_archivo_no_existe(): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');

        $response = $this->get('/media/productos/no-existe.jpg');

        $response->assertNotFound();
    }
}
