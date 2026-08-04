<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Cambia la contraseña de una cuenta desde la consola.
 *
 * El panel admin no tiene "olvidé mi contraseña" y el envío de mails todavía
 * no está configurado, así que sin esto no hay forma de recuperar el acceso.
 */
class CambiarClaveUsuario extends Command
{
    protected $signature = 'usuarios:clave {correo} {clave}';

    protected $description = 'Le pone una contraseña nueva a una cuenta';

    public function handle(): int
    {
        $usuario = User::where('email', $this->argument('correo'))->first();

        if (! $usuario) {
            $this->error("No existe ninguna cuenta con el correo {$this->argument('correo')}.");
            $this->line('Podés ver las cuentas que hay con: php artisan usuarios:listar');

            return self::FAILURE;
        }

        $clave = (string) $this->argument('clave');

        if (mb_strlen($clave) < 8) {
            $this->error('La contraseña tiene que tener al menos 8 caracteres.');

            return self::FAILURE;
        }

        $usuario->forceFill(['password' => Hash::make($clave)])->save();

        $this->info("Listo. {$usuario->email} ({$usuario->role}) ya puede entrar con la contraseña nueva.");

        return self::SUCCESS;
    }
}
