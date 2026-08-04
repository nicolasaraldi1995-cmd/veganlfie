<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Los archivos que subió el Importador quedaron en el disco del panel, que se
 * publica en public/storage: la lista del proveedor, con los costos y los
 * márgenes, se bajaba entera desde /storage/imports/ sin tener cuenta. El
 * formulario ya guarda en el disco privado; esto se lleva los que quedaron.
 */
return new class extends Migration
{
    public function up(): void
    {
        $panel = Storage::disk(config('filament.default_filesystem_disk'));
        $privado = Storage::disk('local');

        foreach ($panel->files('imports') as $archivo) {
            // Se copia antes de borrar: si el destino falla, no se pierde nada.
            if (! $privado->exists($archivo)) {
                $privado->put($archivo, $panel->get($archivo));
            }

            $panel->delete($archivo);
        }

        // La carpeta vacía queda igual: que exista no expone nada, y borrarla
        // en un bucket no significa lo mismo que en un disco de verdad.
    }

    public function down(): void
    {
        // No se devuelven: volver a dejarlos donde cualquiera los baja sería
        // reponer el agujero.
    }
};
