<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('presentaciones', function (Blueprint $table) {
            $table->decimal('precio_costo', 10, 2)->nullable()->after('precio');
            $table->decimal('descuento_porcentaje', 5, 2)->nullable()->after('precio_costo');
            $table->decimal('margen_porcentaje', 5, 2)->nullable()->after('descuento_porcentaje');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presentaciones', function (Blueprint $table) {
            $table->dropColumn(['precio_costo', 'descuento_porcentaje', 'margen_porcentaje']);
        });
    }
};
