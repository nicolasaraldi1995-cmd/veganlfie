<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Se saca la aprobación manual de cuentas: el cliente se registra y ve los
 * precios en el acto. Los precios siguen ocultos para quien no tiene cuenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('aprobado');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('aprobado')->default(true)->after('role');
        });
    }
};
