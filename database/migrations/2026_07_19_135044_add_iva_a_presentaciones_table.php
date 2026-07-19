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
            $table->boolean('iva')->default(false)->after('margen_porcentaje');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presentaciones', function (Blueprint $table) {
            $table->dropColumn('iva');
        });
    }
};
