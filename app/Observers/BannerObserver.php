<?php

namespace App\Observers;

use App\Models\Banner;
use Illuminate\Support\Facades\Storage;

/**
 * Aliviana la imagen del banner sin tocar lo que se ve: la achica para que
 * entre en 1600 x 640 y la guarda en JPEG.
 *
 * No recorta nada. Antes sí lo hacía, y una pieza de diseño más alta que la
 * tira perdía la parte de arriba y la de abajo. El hueco que quede lo tapa el
 * fondo desenfocado del slider, así que la imagen puede tener cualquier forma.
 */
class BannerObserver
{
    private const MAX_ANCHO = 1600;

    private const MAX_ALTO = 640;

    /**
     * Un banner arriba de este peso hace lenta la portada, sobre todo en el
     * celular: los PNG que salen de los editores de diseño pesan 2 o 3 MB.
     */
    private const MAX_PESO = 500 * 1024;

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

        // Se mira el archivo y no si cambió: así un banner viejo y pesado se
        // aliviana con solo abrirlo y guardarlo, sin volver a subirlo.
        if ($this->estaBien($contenido)) {
            $this->anotarMedida($banner, $contenido);

            return;
        }

        $liviana = $this->alivianar($contenido);

        if ($liviana === null) {
            return;
        }

        $this->anotarMedida($banner, $liviana);

        // Se sobreescribe el mismo archivo, sin renombrar. Antes se guardaba
        // como .jpg y se borraba el original: si algún guardado posterior
        // reponía la ruta vieja, la base quedaba apuntando a un archivo que ya
        // no existía y el banner desaparecía. Como el contenido sale en JPEG y
        // el nombre puede decir .png, MediaController mira el contenido del
        // archivo para saber qué tipo servir.
        $disco->put($origen, $liviana, 'public');
    }

    /**
     * La franja del inicio usa esta medida para tomar la forma de la imagen,
     * en vez de obligar a que la imagen tenga una forma determinada.
     */
    private function anotarMedida(Banner $banner, string $contenido): void
    {
        $medidas = @getimagesizefromstring($contenido);

        if ($medidas === false) {
            return;
        }

        $banner->ancho = $medidas[0];
        $banner->alto = $medidas[1];
    }

    private function estaBien(string $contenido): bool
    {
        $medidas = @getimagesizefromstring($contenido);

        return $medidas !== false
            && $medidas[0] <= self::MAX_ANCHO
            && $medidas[1] <= self::MAX_ALTO
            && strlen($contenido) <= self::MAX_PESO;
    }

    /**
     * Achica la imagen hasta que entre en 1600 x 640, respetando su forma.
     * Si ya entra, solo la vuelve a guardar en JPEG para bajarle el peso.
     */
    private function alivianar(string $contenido): ?string
    {
        $original = @imagecreatefromstring($contenido);

        if ($original === false) {
            return null;
        }

        $anchoOriginal = imagesx($original);
        $altoOriginal = imagesy($original);

        // min() para que entre entera; nunca se agranda una imagen chica.
        $escala = min(self::MAX_ANCHO / $anchoOriginal, self::MAX_ALTO / $altoOriginal, 1);
        $ancho = max(1, (int) round($anchoOriginal * $escala));
        $alto = max(1, (int) round($altoOriginal * $escala));

        $destino = imagecreatetruecolor($ancho, $alto);
        // Las imágenes con transparencia quedan sobre blanco: el JPEG no la
        // soporta y sin esto el fondo saldría negro.
        imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255));

        imagecopyresampled($destino, $original, 0, 0, 0, 0, $ancho, $alto, $anchoOriginal, $altoOriginal);

        ob_start();
        imagejpeg($destino, null, 86);
        $salida = (string) ob_get_clean();

        imagedestroy($original);
        imagedestroy($destino);

        return $salida;
    }
}
