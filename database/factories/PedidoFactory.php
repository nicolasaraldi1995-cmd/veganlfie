<?php

namespace Database\Factories;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pedido>
 */
class PedidoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'estado' => 'pending',
            'total' => 0,
            'datos_cliente' => [
                'nombre' => fake()->name(),
                'email' => fake()->safeEmail(),
            ],
        ];
    }
}
