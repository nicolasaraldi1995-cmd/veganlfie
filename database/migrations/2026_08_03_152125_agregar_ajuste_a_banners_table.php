<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elegir entre recortar la imagen para llenar la franja (lo de siempre) o
 * mostrarla entera sin cortar nada. Con imágenes más anchas que el banner,
 * elegir la posición no alcanzaba: se recortaba por los costados igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('ajuste')->default('cover')->after('posicion');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('ajuste');
        });
    }
};
