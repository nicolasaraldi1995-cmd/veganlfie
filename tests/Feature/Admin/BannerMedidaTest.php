<?php

namespace Tests\Feature\Admin;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BannerMedidaTest extends TestCase
{
    use RefreshDatabase;

    private function guardarImagen(string $nombre, int $ancho, int $alto): string
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

    /**
     * @return array<string, array{int, int, bool}>
     */
    public static function medidas(): array
    {
        return [
            'más alta que la franja' => [1983, 793, true],
            'casi cuadrada' => [740, 493, true],
            'más chica que la franja' => [600, 200, true],
            // La que ya viene en medida no se toca: no hay nada que recortar.
            'ya en 1600x400' => [1600, 400, false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('medidas')]
    public function test_la_imagen_queda_en_1600x400_sea_cual_sea_la_que_suban(int $ancho, int $alto, bool $seRecorta): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');

        $ruta = $this->guardarImagen('subida.png', $ancho, $alto);

        $banner = Banner::create([
            'imagen' => $ruta,
            'destino_tipo' => 'url',
            'destino_valor' => 'https://example.com',
            'orden' => 0,
            'activo' => true,
        ]);

        // Al recortar sale en JPEG, así que el archivo cambia de extensión.
        $this->assertSame($seRecorta ? 'banners/subida.jpg' : $ruta, $banner->imagen);

        if ($seRecorta) {
            $this->assertFalse(Storage::disk('public')->exists($ruta), 'El archivo original quedó ocupando lugar.');
        }

        [$anchoFinal, $altoFinal] = getimagesizefromstring(Storage::disk('public')->get($banner->imagen));

        $this->assertSame(1600, $anchoFinal);
        $this->assertSame(400, $altoFinal);
    }

    public function test_no_vuelve_a_procesar_una_imagen_que_ya_esta_en_medida(): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');

        $banner = Banner::create([
            'imagen' => $this->guardarImagen('uno.png', 900, 900),
            'destino_tipo' => 'url',
            'destino_valor' => 'https://example.com',
            'orden' => 0,
            'activo' => true,
        ]);

        $antes = Storage::disk('public')->get($banner->imagen);

        $banner->update(['orden' => 5]);

        $this->assertSame('banners/uno.jpg', $banner->fresh()->imagen);
        $this->assertSame($antes, Storage::disk('public')->get($banner->imagen));
    }

    /**
     * Los banners que ya estaban cargados se acomodan con solo abrirlos y
     * guardarlos, sin tener que volver a subir la imagen.
     */
    public function test_un_banner_viejo_se_acomoda_al_guardarlo(): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');

        $banner = Banner::withoutEvents(fn () => Banner::create([
            'imagen' => $this->guardarImagen('viejo.png', 1983, 793),
            'destino_tipo' => 'url',
            'destino_valor' => 'https://example.com',
            'orden' => 0,
            'activo' => true,
        ]));

        $banner->save();

        [$ancho, $alto] = getimagesizefromstring(Storage::disk('public')->get($banner->fresh()->imagen));

        $this->assertSame(1600, $ancho);
        $this->assertSame(400, $alto);
    }
}
