<?php

namespace App\Observers;

use App\Models\Marca;
use Illuminate\Support\Facades\Storage;

/**
 * Deja todo logo de marca en un cuadrado, que es como se muestra en la web:
 * dentro de un círculo.
 *
 * El que ya viene cuadrado no se toca: ese es el recorte que eligió a mano
 * quien lo subió, y tiene que verse tal cual. Al alargado se le agrega blanco
 * arriba y abajo hasta hacerlo cuadrado, encogiéndolo lo justo para que entre
 * completo dentro del círculo sin que se le corten las puntas.
 *
 * Así la página no necesita ningún margen de seguridad, que era lo que hacía
 * que un logo cuadrado se viera chico con un anillo blanco alrededor.
 */
class MarcaObserver
{
    private const LADO = 600;

    public function saving(Marca $marca): void
    {
        if (blank($marca->logo)) {
            return;
        }

        $disco = Storage::disk(config('filament.default_filesystem_disk'));
        $origen = $marca->logo;

        if (! $disco->exists($origen)) {
            return;
        }

        $contenido = $disco->get($origen) ?? '';
        $medidas = @getimagesizefromstring($contenido);

        if ($medidas === false || $medidas[0] === $medidas[1]) {
            return;
        }

        $cuadrada = $this->encuadrar($contenido, $medidas[0], $medidas[1]);

        if ($cuadrada === null) {
            return;
        }

        // Se sobreescribe el mismo archivo, sin renombrar: renombrar dejaba a
        // la base apuntando a un archivo borrado si algún guardado posterior
        // reponía la ruta vieja. MediaController mira el contenido, así que no
        // importa que el nombre diga .png y adentro haya un JPEG.
        $disco->put($origen, $cuadrada, 'public');
    }

    private function encuadrar(string $contenido, int $anchoOriginal, int $altoOriginal): ?string
    {
        $original = @imagecreatefromstring($contenido);

        if ($original === false) {
            return null;
        }

        // El logo tiene que entrar en el círculo inscripto en el cuadrado, no
        // en el cuadrado entero: si no, las esquinas quedan afuera del círculo
        // y se cortan. Para una imagen de proporción r, el rectángulo más
        // grande de esa forma que entra en un círculo de diámetro D mide
        // D*r/raíz(r²+1) de ancho.
        $proporcion = $anchoOriginal / $altoOriginal;
        $ancho = (int) round(self::LADO * $proporcion / sqrt($proporcion ** 2 + 1));
        $alto = (int) round($ancho / $proporcion);

        $destino = imagecreatetruecolor(self::LADO, self::LADO);
        // Blanco: es el color del círculo en la web, así el relleno no se nota.
        imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255));

        imagecopyresampled(
            $destino,
            $original,
            (int) round((self::LADO - $ancho) / 2),
            (int) round((self::LADO - $alto) / 2),
            0,
            0,
            $ancho,
            $alto,
            $anchoOriginal,
            $altoOriginal
        );

        ob_start();
        imagejpeg($destino, null, 90);
        $salida = (string) ob_get_clean();

        imagedestroy($original);
        imagedestroy($destino);

        return $salida;
    }
}
