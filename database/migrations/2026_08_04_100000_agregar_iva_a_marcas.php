<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interruptor de IVA por marca: al prenderlo, todos los productos de esa marca
 * pasan a tener IVA y su precio sube el 21%. Al apagarlo, vuelven atrás.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marcas', function (Blueprint $tabla) {
            $tabla->boolean('iva')->default(false)->after('margen_porcentaje');
        });
    }

    public function down(): void
    {
        Schema::table('marcas', function (Blueprint $tabla) {
            $tabla->dropColumn('iva');
        });
    }
};
