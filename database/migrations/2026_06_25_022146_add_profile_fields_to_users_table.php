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
        Schema::table('users', function (Blueprint $table) {
            $table->string('negocio')->nullable()->after('name');
            $table->string('tipo_cliente')->default('particular')->after('negocio');
            $table->string('celular')->nullable()->after('email');
            $table->string('direccion')->nullable()->after('celular');
            $table->string('ciudad')->nullable()->after('direccion');
            $table->string('provincia')->nullable()->after('ciudad');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['negocio', 'tipo_cliente', 'celular', 'direccion', 'ciudad', 'provincia']);
        });
    }
};
