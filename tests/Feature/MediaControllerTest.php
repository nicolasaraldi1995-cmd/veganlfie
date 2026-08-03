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

    /**
     * Windows guarda los JPEG como .jfif: servirlos como octet-stream hacía
     * que el navegador no mostrara la imagen.
     */
    public function test_sirve_un_jfif_como_imagen(): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');
        Storage::disk('public')->put('banners/tapa.jfif', 'contenido');

        $this->get('/media/banners/tapa.jfif')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    /**
     * Una extensión que no está en la lista se resuelve mirando el contenido,
     * en vez de caer siempre en octet-stream.
     */
    public function test_deduce_el_tipo_de_una_extension_desconocida(): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');
        // GIF de 1x1 con extensión inventada.
        Storage::disk('public')->put('banners/raro.imagen', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));

        $this->get('/media/banners/raro.imagen')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');
    }

    public function test_404_si_el_archivo_no_existe(): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');

        $response = $this->get('/media/productos/no-existe.jpg');

        $response->assertNotFound();
    }
}
