<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El banner recorta la imagen para llenar una franja ancha y baja, y hasta
 * ahora siempre recortaba desde el centro: si lo importante estaba arriba o
 * abajo, quedaba afuera. Esto deja elegir qué parte se conserva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('posicion')->default('center')->after('imagen');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('posicion');
        });
    }
};
