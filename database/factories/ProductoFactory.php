<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(3, true),
            'marca_id' => Marca::factory(),
            'categoria_id' => Categoria::factory(),
            'sin_tacc' => false,
            'frio' => false,
            'congelado' => false,
            'nuevo' => false,
            'activo' => true,
        ];
    }
}
