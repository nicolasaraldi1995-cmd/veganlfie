<?php

namespace Database\Factories;

use App\Models\Presentacion;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Presentacion>
 */
class PresentacionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'unidad' => '500gr',
            'precio' => fake()->randomFloat(2, 500, 5000),
            'stock' => 10,
            'activo' => true,
        ];
    }
}
