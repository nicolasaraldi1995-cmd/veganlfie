<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('aprobado')->default(false)->after('role');
        });

        // Los clientes que ya venían trabajando con la distribuidora se dan por
        // aprobados: la revisión manual aplica de acá en adelante, para no
        // dejarlos afuera de un día para el otro.
        DB::table('users')->update(['aprobado' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('aprobado');
        });
    }
};
