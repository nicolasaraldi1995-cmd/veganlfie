<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // productos.slug nunca tuvo unique(): dos productos con el mismo nombre
        // (o con nombres que generan el mismo slug) quedaban con slugs repetidos,
        // y {producto:slug} siempre resuelve al primero -- el resto era
        // inalcanzable desde la web. Se renombra a todos menos al de id más
        // chico (el que hoy efectivamente resuelve esa URL) antes de poder
        // agregar la restricción única.
        $duplicados = DB::table('productos')
            ->select('slug')
            ->groupBy('slug')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('slug');

        foreach ($duplicados as $slugBase) {
            $ids = DB::table('productos')
                ->where('slug', $slugBase)
                ->orderBy('id')
                ->pluck('id');

            $sufijo = 2;
            foreach ($ids->skip(1) as $id) {
                $nuevoSlug = "{$slugBase}-{$sufijo}";

                while (DB::table('productos')->where('slug', $nuevoSlug)->exists()) {
                    $sufijo++;
                    $nuevoSlug = "{$slugBase}-{$sufijo}";
                }

                DB::table('productos')->where('id', $id)->update(['slug' => $nuevoSlug]);
                $sufijo++;
            }
        }

        Schema::table('productos', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });
    }
};
