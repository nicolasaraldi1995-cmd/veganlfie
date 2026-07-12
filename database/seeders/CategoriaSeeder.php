<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = json_decode(file_get_contents(__DIR__.'/_categorias.json'), true);

        foreach ($categorias as $i => $nombre) {
            Categoria::firstOrCreate(['nombre' => $nombre], ['orden' => $i]);
        }
    }
}
