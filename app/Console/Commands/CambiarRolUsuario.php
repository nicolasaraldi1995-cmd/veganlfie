<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Cambia el rol de una cuenta desde la consola.
 *
 * Es la salida de emergencia: si el único admin se degrada o se borra sin
 * querer, el panel deja de dejarlo entrar y no hay forma de arreglarlo desde la
 * app. Antes había que tocar la base a mano.
 */
class CambiarRolUsuario extends Command
{
    protected $signature = 'usuarios:rol {correo} {rol}';

    protected $description = 'Le cambia el rol a una cuenta (admin, operador o cliente)';

    private const ROLES = ['admin', 'operador', 'cliente'];

    public function handle(): int
    {
        $rol = (string) $this->argument('rol');

        if (! in_array($rol, self::ROLES, true)) {
            $this->error("El rol tiene que ser uno de: ".implode(', ', self::ROLES).'.');

            return self::FAILURE;
        }

        $usuario = User::where('email', $this->argument('correo'))->first();

        if (! $usuario) {
            $this->error("No existe ninguna cuenta con el correo {$this->argument('correo')}.");
            $this->line('Podés ver las cuentas que hay con: php artisan usuarios:listar');

            return self::FAILURE;
        }

        $usuario->forceFill(['role' => $rol])->save();

        $this->info("Listo. {$usuario->email} ahora es {$rol}.");

        return self::SUCCESS;
    }
}
