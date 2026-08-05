<?php

namespace Tests\Feature\Admin;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BannerMedidaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un degradé suave, que es como se comporta una foto de verdad: el PNG lo
     * guarda mal y el JPEG lo guarda bien. Con un color plano la prueba de peso
     * no significaría nada, porque ahí el PNG siempre gana.
     */
    private function guardarImagen(string $nombre, int $ancho, int $alto): string
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        for ($x = 0; $x < $ancho; $x++) {
            for ($y = 0; $y < $alto; $y++) {
                imagesetpixel($imagen, $x, $y, imagecolorallocate(
                    $imagen,
                    (int) (255 * $x / $ancho),
                    (int) (255 * $y / $alto),
                    (int) (255 * ($x + $y) / ($ancho + $alto))
                ));
            }
        }
        ob_start();
        imagepng($imagen);
        $contenido = (string) ob_get_clean();
        imagedestroy($imagen);

        Storage::disk('public')->put("banners/{$nombre}", $contenido);

        return "banners/{$nombre}";
    }

    private function crearBanner(string $ruta): Banner
    {
        return Banner::create([
            'imagen' => $ruta,
            'destino_tipo' => 'url',
            'destino_valor' => 'https://example.com',
            'orden' => 0,
            'activo' => true,
        ]);
    }

    /**
     * @return array<string, array{int, int, float}>
     */
    public static function medidas(): array
    {
        return [
            // La pieza de las mermeladas: más alta que la tira. Antes se le
            // recortaba el 37% (se perdían el título y la fila de abajo).
            'más alta que la tira' => [1983, 793, 2.50],
            'casi cuadrada' => [740, 493, 1.50],
            'bien alargada' => [1983, 496, 4.00],
            'ya en medida' => [1600, 640, 2.50],
        ];
    }

    #[DataProvider('medidas')]
    public function test_la_imagen_nunca_se_recorta(int $ancho, int $alto, float $proporcion): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');

        $banner = $this->crearBanner($this->guardarImagen('subida.png', $ancho, $alto));

        [$anchoFinal, $altoFinal] = getimagesizefromstring(Storage::disk('public')->get($banner->imagen));

        // Lo único que importa: la forma de la imagen se conserva, así que no
        // se perdió ni un pedazo del diseño.
        $this->assertEqualsWithDelta($proporcion, $anchoFinal / $altoFinal, 0.02);
        $this->assertLessThanOrEqual(1600, $anchoFinal);
        $this->assertLessThanOrEqual(640, $altoFinal);
    }

    public function test_una_imagen_pesada_se_aliviana(): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');

        $ruta = $this->guardarImagen('pesada.png', 3000, 1200);
        $pesoOriginal = strlen(Storage::disk('public')->get($ruta));

        $banner = $this->crearBanner($ruta);

        // El archivo se sobreescribe en su mismo nombre: renombrarlo dejaba a
        // la base apuntando a un archivo que ya no existía.
        $this->assertSame($ruta, $banner->imagen);
        $this->assertTrue(Storage::disk('public')->exists($banner->imagen));
        $this->assertLessThan($pesoOriginal, strlen(Storage::disk('public')->get($banner->imagen)));
    }

    public function test_no_vuelve_a_procesar_una_imagen_que_ya_esta_bien(): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');

        $banner = $this->crearBanner($this->guardarImagen('uno.png', 900, 300));
        $antes = Storage::disk('public')->get($banner->imagen);

        $banner->update(['orden' => 5]);

        $this->assertSame($antes, Storage::disk('public')->get($banner->fresh()->imagen));
    }
}
