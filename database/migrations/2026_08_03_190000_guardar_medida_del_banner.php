<?php

use App\Models\Banner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda el ancho y el alto de la imagen de cada banner.
 *
 * Con eso la franja del inicio puede tomar la forma de la imagen en vez de
 * imponerle una: así entra perfecta cualquier medida que se suba, sin tener
 * que acordarse de ninguna proporción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $tabla) {
            $tabla->unsignedInteger('ancho')->nullable()->after('imagen');
            $tabla->unsignedInteger('alto')->nullable()->after('ancho');
        });

        // Volver a guardar completa la medida de los que ya estaban cargados.
        Banner::withoutTimestamps(function () {
            Banner::query()->whereNotNull('imagen')->each(function (Banner $banner) {
                try {
                    $banner->save();
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $tabla) {
            $tabla->dropColumn(['ancho', 'alto']);
        });
    }
};
