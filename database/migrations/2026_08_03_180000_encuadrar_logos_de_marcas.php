<?php

use App\Models\Marca;
use Illuminate\Database\Migrations\Migration;

/**
 * Vuelve a guardar cada marca para que MarcaObserver encuadre su logo.
 *
 * Los logos cargados antes del encuadre son de cualquier forma, así que en la
 * web se veían chicos y con un anillo blanco alrededor. Con esto se acomodan
 * todos de una, sin tener que entrar marca por marca a darle Guardar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sin tocar updated_at: esto es un arreglo del archivo, no un cambio
        // que haya hecho alguien.
        Marca::withoutTimestamps(function () {
            Marca::query()->whereNotNull('logo')->chunkById(50, function ($marcas) {
                foreach ($marcas as $marca) {
                    try {
                        $marca->save();
                    } catch (\Throwable $e) {
                        // Un logo roto no puede cortar el deploy: se salta y
                        // queda como estaba.
                        report($e);
                    }
                }
            });
        });
    }

    public function down(): void
    {
        // No tiene vuelta atrás: el archivo original se reemplaza.
    }
};
