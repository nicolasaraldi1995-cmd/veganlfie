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
        Schema::create('presentaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('unidad');
            $table->string('sku')->nullable()->unique();
            $table->decimal('precio', 10, 2);
            $table->integer('stock')->default(0);
            $table->boolean('activo')->default(true);
            $table->decimal('oferta_porcentaje', 5, 2)->nullable();
            $table->decimal('oferta_precio', 10, 2)->nullable();
            $table->date('oferta_inicio')->nullable();
            $table->date('oferta_fin')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presentaciones');
    }
};
