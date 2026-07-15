<?php

namespace App\Console\Commands;

use App\Models\Producto;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('app:aplicar-fotos-productos-desde-zip {url}')]
#[Description('Descarga un .zip con fotos de producto (cada archivo nombrado {producto_id}.{ext}), las sube al disco configurado y setea Producto.imagen.')]
class AplicarFotosProductosDesdeZip extends Command
{
    public function handle(): int
    {
        $url = $this->argument('url');

        $zipPath = tempnam(sys_get_temp_dir(), 'fotos_').'.zip';
        $extractDir = sys_get_temp_dir().'/fotos_extract_'.uniqid();
        mkdir($extractDir);

        $this->info("Descargando {$url}...");
        $contents = file_get_contents($url);
        if ($contents === false) {
            $this->error('No se pudo descargar el zip.');

            return self::FAILURE;
        }
        file_put_contents($zipPath, $contents);

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

        foreach (glob($extractDir.'/*') as $file) {
            $productoId = (int) pathinfo($file, PATHINFO_FILENAME);
            $ext = pathinfo($file, PATHINFO_EXTENSION);

            $producto = Producto::find($productoId);
            if (! $producto) {
                $this->warn("Producto #{$productoId} no encontrado, se salteó.");
                $fail++;

                continue;
            }

            $destPath = 'productos/'.$producto->slug.'.'.$ext;
            Storage::disk($disk)->put($destPath, file_get_contents($file));
            $producto->update(['imagen' => $destPath]);
            $ok++;
        }

        array_map('unlink', glob($extractDir.'/*'));
        rmdir($extractDir);

        $this->newLine();
        $this->info("Aplicadas: {$ok}");
        $this->info("Falladas: {$fail}");

        return self::SUCCESS;
    }
}
