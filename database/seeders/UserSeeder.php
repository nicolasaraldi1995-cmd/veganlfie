<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Las dos cuentas del equipo. La contraseña era fija, "password", escrita acá
 * a la vista de cualquiera que abriera el repositorio: quien supiera el correo
 * entraba al panel con los costos, la caja y los datos de todos los clientes.
 *
 * Ahora sale una al azar y se imprime al sembrar. Si querés elegirla vos,
 * poné CLAVE_ADMIN y CLAVE_OPERADOR en el .env antes de correr el seeder.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Admin VEGANLIFE', 'admin@veganlife.com', 'admin', 'CLAVE_ADMIN'],
            ['Operador VEGANLIFE', 'operador@veganlife.com', 'operador', 'CLAVE_OPERADOR'],
        ] as [$nombre, $email, $rol, $variable]) {
            $clave = env($variable) ?: Str::password(16);

            User::create([
                'name' => $nombre,
                'email' => $email,
                'password' => bcrypt($clave),
                'role' => $rol,
            ]);

            // Se imprime una sola vez, acá: no queda guardada en ningún lado.
            $this->command?->warn("  {$email} → {$clave}");
        }

        $this->command?->warn('  Anotá esas contraseñas: no se vuelven a mostrar.');
    }
}
