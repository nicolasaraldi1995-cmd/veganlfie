<?php

use App\Models\Banner;
use App\Models\Marca;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Repara los registros que quedaron apuntando a un archivo que ya no existe.
 *
 * Al reprocesar una imagen, el servidor la guardaba con otro nombre (.jpg) y
 * borraba la original. Si después algún guardado reponía la ruta vieja, el
 * registro quedaba apuntando a un archivo borrado y la imagen desaparecía.
 *
 * Acá se busca el archivo gemelo (mismo nombre, otra extensión) y se vuelve a
 * dejar el contenido en la ruta que dice la base. El renombrado ya no se hace
 * más, así que esto corre una sola vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        $disco = Storage::disk(config('filament.default_filesystem_disk'));

        foreach ([[Banner::class, 'imagen', 'banners'], [Marca::class, 'logo', 'marcas']] as [$modelo, $campo, $carpeta]) {
            $archivos = collect($disco->files($carpeta));

            $modelo::withoutTimestamps(function () use ($modelo, $campo, $disco, $archivos) {
                $modelo::query()->whereNotNull($campo)->each(function ($registro) use ($campo, $disco, $archivos) {
                    $ruta = $registro->{$campo};

                    if (blank($ruta) || $disco->exists($ruta)) {
                        return;
                    }

                    $nombre = pathinfo($ruta, PATHINFO_FILENAME);
                    $gemelo = $archivos->first(fn ($a) => pathinfo($a, PATHINFO_FILENAME) === $nombre);

                    if ($gemelo === null) {
                        return;
                    }

                    try {
                        $disco->put($ruta, $disco->get($gemelo), 'public');
                        $disco->delete($gemelo);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                });
            });
        }
    }

    public function down(): void
    {
        // Es una reparación de datos: no tiene vuelta atrás.
    }
};
