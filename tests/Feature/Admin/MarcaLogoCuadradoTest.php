<?php

namespace Tests\Feature\Admin;

use App\Models\Marca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * En la web el logo se muestra dentro de un círculo. Para que lo que se recorta
 * en el admin sea exactamente lo que se ve, el archivo tiene que ser cuadrado.
 */
class MarcaLogoCuadradoTest extends TestCase
{
    use RefreshDatabase;

    private function guardarLogo(string $nombre, int $ancho, int $alto): string
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, 220, 40, 120));
        ob_start();
        imagepng($imagen);
        $contenido = (string) ob_get_clean();
        imagedestroy($imagen);

        Storage::disk('public')->put("marcas/{$nombre}", $contenido);

        return "marcas/{$nombre}";
    }

    public function test_un_logo_alargado_se_encuadra_sin_recortarse(): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');

        $marca = Marca::create([
            'nombre' => 'Alargada',
            'logo' => $this->guardarLogo('ancho.png', 738, 264),
            'activo' => true,
        ]);

        $contenido = Storage::disk('public')->get($marca->logo);
        [$ancho, $alto] = getimagesizefromstring($contenido);

        $this->assertSame($ancho, $alto, 'El logo tendría que haber quedado cuadrado.');

        // Lo que importa: el dibujo entra completo dentro del círculo inscripto.
        // Para 738x264 (2.80:1) el ancho máximo que entra es 0.94 del lado.
        $margen = $this->margenLateral($contenido, $ancho, $alto);
        $this->assertGreaterThan(0, $margen, 'El logo llega al borde: el círculo le va a cortar las puntas.');
    }

    public function test_un_logo_ya_cuadrado_no_se_toca(): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');

        $ruta = $this->guardarLogo('cuadrado.png', 600, 600);
        $antes = Storage::disk('public')->get($ruta);

        $marca = Marca::create(['nombre' => 'Cuadrada', 'logo' => $ruta, 'activo' => true]);

        // Es el recorte que eligió a mano quien lo subió: tiene que verse igual.
        $this->assertSame($ruta, $marca->logo);
        $this->assertSame($antes, Storage::disk('public')->get($marca->logo));
    }

    /**
     * Cuántos píxeles de blanco quedan a la izquierda, en la mitad de la altura.
     */
    private function margenLateral(string $contenido, int $ancho, int $alto): int
    {
        $imagen = imagecreatefromstring($contenido);
        $medio = (int) ($alto / 2);
        $blancos = 0;

        for ($x = 0; $x < $ancho; $x++) {
            $color = imagecolorat($imagen, $x, $medio);
            $esBlanco = (($color >> 16) & 0xFF) > 240
                && (($color >> 8) & 0xFF) > 240
                && ($color & 0xFF) > 240;

            if (! $esBlanco) {
                break;
            }

            $blancos++;
        }

        imagedestroy($imagen);

        return $blancos;
    }
}
