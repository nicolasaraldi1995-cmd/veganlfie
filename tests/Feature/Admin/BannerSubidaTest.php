<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\BannerResource\Pages\CreateBanner;
use App\Filament\Resources\BannerResource\Pages\EditBanner;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lo que la base dice y lo que hay en el disco tienen que coincidir. Si no,
 * el banner queda apuntando a un archivo que no existe y la portada muestra
 * un hueco.
 */
class BannerSubidaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');

        return User::factory()->create(['role' => 'admin']);
    }

    public function test_al_subir_un_banner_el_archivo_guardado_existe(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(CreateBanner::class)
            ->fillForm([
                // Más alta que el tope de 640: obliga al servidor a reprocesar
                // el archivo, que es donde estaba el problema.
                'imagen' => [UploadedFile::fake()->image('banner.png', 2400, 1200)],
                'destino_tipo' => 'url',
                'destino_valor' => 'https://example.com',
                'orden' => 0,
                'activo' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $banner = Banner::firstOrFail();

        $this->assertTrue(
            Storage::disk('public')->exists($banner->imagen),
            "La base apunta a {$banner->imagen} pero ese archivo no existe."
        );
    }

    public function test_al_editar_un_banner_el_archivo_guardado_sigue_existiendo(): void
    {
        $admin = $this->admin();

        $banner = Banner::create([
            'imagen' => $this->subirImagen('viejo.png', 2400, 1200),
            'destino_tipo' => 'url',
            'destino_valor' => 'https://example.com',
            'orden' => 0,
            'activo' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(EditBanner::class, ['record' => $banner->getKey()])
            ->fillForm(['orden' => 3])
            ->call('save')
            ->assertHasNoFormErrors();

        $banner->refresh();

        $this->assertTrue(
            Storage::disk('public')->exists($banner->imagen),
            "La base apunta a {$banner->imagen} pero ese archivo no existe."
        );
    }

    /**
     * El modo de falla exacto de producción. Filament guarda el registro más
     * de una vez durante un mismo envío del formulario, con la ruta que trae
     * el estado del formulario. Cuando el servidor renombraba el archivo al
     * reprocesarlo, ese segundo guardado reponía la ruta vieja -- que el
     * servidor ya había borrado-- y el banner desaparecía de la portada.
     */
    public function test_guardar_dos_veces_no_deja_la_ruta_apuntando_a_un_archivo_borrado(): void
    {
        $this->admin();

        $ruta = $this->subirImagen('doble.png', 2400, 1200);

        $banner = Banner::create([
            'imagen' => $ruta,
            'destino_tipo' => 'url',
            'destino_valor' => 'https://example.com',
            'orden' => 0,
            'activo' => true,
        ]);

        // El segundo guardado, con la ruta original del formulario.
        $banner->imagen = $ruta;
        $banner->save();

        $guardado = $banner->fresh();

        $this->assertTrue(
            Storage::disk('public')->exists($guardado->imagen),
            "La base apunta a {$guardado->imagen} pero ese archivo no existe."
        );

        // Y se sirve como imagen aunque el nombre diga .png y adentro haya JPEG.
        $this->get('/media/'.$guardado->imagen)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    private function subirImagen(string $nombre, int $ancho, int $alto): string
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, 40, 120, 155));
        ob_start();
        imagepng($imagen);
        $contenido = (string) ob_get_clean();
        imagedestroy($imagen);

        Storage::disk('public')->put("banners/{$nombre}", $contenido);

        return "banners/{$nombre}";
    }
}
