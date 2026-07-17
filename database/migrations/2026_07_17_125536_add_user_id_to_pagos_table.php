<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropForeign(['pedido_id']);
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->unsignedBigInteger('pedido_id')->nullable()->change();
            $table->foreignId('user_id')->nullable()->after('pedido_id')->constrained('users')->restrictOnDelete();
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->foreign('pedido_id')->references('id')->on('pedidos')->cascadeOnDelete();
        });

        // Pagos existentes ya estaban ligados a un pedido: copiamos el user_id
        // del pedido para que "total pagado por cliente" sea siempre un simple
        // WHERE user_id, sin tener que unir con pedidos. Se hace vía Eloquent
        // (no un UPDATE...JOIN crudo) porque esa sintaxis es de MySQL y los
        // tests corren sobre SQLite.
        DB::table('pagos')
            ->whereNull('user_id')
            ->whereNotNull('pedido_id')
            ->get(['id', 'pedido_id'])
            ->each(function ($pago) {
                $userId = DB::table('pedidos')->where('id', $pago->pedido_id)->value('user_id');
                if ($userId) {
                    DB::table('pagos')->where('id', $pago->id)->update(['user_id' => $userId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropForeign(['pedido_id']);
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->unsignedBigInteger('pedido_id')->nullable(false)->change();
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->foreign('pedido_id')->references('id')->on('pedidos')->cascadeOnDelete();
        });
    }
};
