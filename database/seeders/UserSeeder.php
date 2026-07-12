<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin VEGANLIFE',
            'email' => 'admin@veganlife.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Operador VEGANLIFE',
            'email' => 'operador@veganlife.com',
            'password' => bcrypt('password'),
            'role' => 'operador',
        ]);
    }
}
