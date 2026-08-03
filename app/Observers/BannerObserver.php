<?php

namespace App\Observers;

use App\Models\Banner;
use Illuminate\Support\Facades\Storage;

/**
 * Deja toda imagen de banner en 1600 x 400, la medida de la franja del
 * inicio. Se hace acá, en el servidor, y no en el navegador: así no importa
 * qué medida ni qué formato suba el usuario, la franja siempre queda llena
 * y sin espacios a los costados.
 */
class BannerObserver
{
    private const ANCHO = 1600;

    private const ALTO = 400;

    public function saving(Banner $banner): void
    {
        if (blank($banner->imagen)) {
            return;
        }

        $disco = Storage::disk(config('filament.default_filesystem_disk'));
        $origen = $banner->imagen;

        if (! $disco->exists($origen)) {
            return;
        }

        $contenido = $disco->get($origen) ?? '';

        // Se mira la medida en vez de si cambió el archivo: así un banner
        // viejo se acomoda con solo abrirlo y guardarlo, sin volver a subirlo.
        if ($this->yaMide($contenido)) {
            return;
        }

        $recortada = $this->recortar($contenido);

        if ($recortada === null) {
            return;
        }

        // Sale siempre en JPEG, así que el archivo cambia de nombre: dejar la
        // extensión vieja haría que el navegador reciba un tipo equivocado.
        $destino = preg_replace('/\.[^.]+$/', '', $origen).'.jpg';

        $disco->put($destino, $recortada, 'public');

        if ($destino !== $origen) {
            $disco->delete($origen);
        }

        $banner->imagen = $destino;
    }

    private function yaMide(string $contenido): bool
    {
        $medidas = @getimagesizefromstring($contenido);

        return $medidas !== false
            && $medidas[0] === self::ANCHO
            && $medidas[1] === self::ALTO;
    }

    /**
     * Recorta al centro para llenar 1600 x 400 sin deformar la imagen.
     */
    private function recortar(string $contenido): ?string
    {
        $original = @imagecreatefromstring($contenido);

        if ($original === false) {
            return null;
        }

        $anchoOriginal = imagesx($original);
        $altoOriginal = imagesy($original);

        // Escala para que el lado más "chico" cubra la franja, y del resto se
        // toma la parte del medio.
        $escala = max(self::ANCHO / $anchoOriginal, self::ALTO / $altoOriginal);
        $anchoTomado = (int) round(self::ANCHO / $escala);
        $altoTomado = (int) round(self::ALTO / $escala);

        $destino = imagecreatetruecolor(self::ANCHO, self::ALTO);
        imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255));

        imagecopyresampled(
            $destino,
            $original,
            0,
            0,
            (int) round(($anchoOriginal - $anchoTomado) / 2),
            (int) round(($altoOriginal - $altoTomado) / 2),
            self::ANCHO,
            self::ALTO,
            $anchoTomado,
            $altoTomado
        );

        ob_start();
        imagejpeg($destino, null, 88);
        $salida = (string) ob_get_clean();

        imagedestroy($original);
        imagedestroy($destino);

        return $salida;
    }
}
