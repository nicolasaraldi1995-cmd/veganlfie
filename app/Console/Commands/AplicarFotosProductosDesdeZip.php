<?php

namespace App\Console\Commands;

use App\Models\Producto;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

#[Signature('app:aplicar-fotos-productos-desde-zip {url}')]
#[Description('Descarga un .zip con fotos de producto (cada archivo nombrado {producto_id}.{ext}), las sube al disco configurado y setea Producto.imagen.')]
class AplicarFotosProductosDesdeZip extends Command
{
    public function handle(): int
    {
        $url = (string) $this->argument('url');

        // Sólo http/https, por lo mismo que el importador desde URL: nada de
        // file://, php:// ni la dirección de metadatos de la nube.
        if (! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            $this->error('La URL tiene que empezar con http:// o https://.');

            return self::FAILURE;
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'fotos_').'.zip';
        $extractDir = sys_get_temp_dir().'/fotos_extract_'.uniqid();
        mkdir($extractDir);

        $this->info("Descargando {$url}...");
        $respuesta = Http::timeout(120)->get($url);
        if (! $respuesta->successful()) {
            $this->error('No se pudo descargar el zip.');

            return self::FAILURE;
        }
        file_put_contents($zipPath, $respuesta->body());

        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            $this->error('No se pudo abrir el zip.');

            return self::FAILURE;
        }
        $zip->extractTo($extractDir);
        $zip->close();
        unlink($zipPath);

        $disk = config('filament.default_filesystem_disk');
        $ok = 0;
        $fail = 0;

        // glob() devuelve false si falla: sin el ?: [] el array_map de más
        // abajo cortaba el comando con un error fatal.
        foreach (glob($extractDir.'/*') ?: [] as $file) {
            $productoId = (int) pathinfo($file, PATHINFO_FILENAME);
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            // Sólo imágenes, y no por el nombre: se mira el contenido. Un zip
            // con una entrada "12.php" escribía productos/<slug>.php en el disco
            // del panel, que se sirve desde public/storage; si el servidor
            // ejecuta PHP ahí, era ejecución de código.
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'avif'], true)) {
                $this->warn("{$file}: no es una imagen, se salteó.");
                $fail++;

                continue;
            }

            $contenido = (string) file_get_contents($file);
            if (@getimagesizefromstring($contenido) === false) {
                $this->warn("{$file}: el contenido no es una imagen válida, se salteó.");
                $fail++;

                continue;
            }

            $producto = Producto::find($productoId);
            if (! $producto) {
                $this->warn("Producto #{$productoId} no encontrado, se salteó.");
                $fail++;

                continue;
            }

            $destPath = 'productos/'.$producto->slug.'.'.$ext;
            Storage::disk($disk)->put($destPath, $contenido);
            $producto->update(['imagen' => $destPath]);
            $ok++;
        }

        array_map('unlink', glob($extractDir.'/*') ?: []);
        rmdir($extractDir);

        $this->newLine();
        $this->info("Aplicadas: {$ok}");
        $this->info("Falladas: {$fail}");

        return self::SUCCESS;
    }
}
