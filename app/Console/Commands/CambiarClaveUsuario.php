<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Cambia la contraseña de una cuenta desde la consola.
 *
 * El panel admin no tiene "olvidé mi contraseña" y el envío de mails todavía
 * no está configurado, así que sin esto no hay forma de recuperar el acceso.
 */
class CambiarClaveUsuario extends Command
{
    // La clave es opcional: si no se pasa se pide sin que se vea, para que no
    // quede en el historial del shell ni en la lista de procesos.
    protected $signature = 'usuarios:clave {correo} {clave?}';

    protected $description = 'Le pone una contraseña nueva a una cuenta';

    public function handle(): int
    {
        $usuario = User::where('email', $this->argument('correo'))->first();

        if (! $usuario) {
            $this->error("No existe ninguna cuenta con el correo {$this->argument('correo')}.");
            $this->line('Podés ver las cuentas que hay con: php artisan usuarios:listar');

            return self::FAILURE;
        }

        $clave = $this->argument('clave');

        if ($clave !== null) {
            $this->warn('Ojo: la clave pasada como argumento queda en el historial del shell. La próxima vez dejala en blanco y te la pido acá.');
        } else {
            $clave = (string) $this->secret('Contraseña nueva (no se ve al tipear)');
        }

        // La misma política que el panel (ver AppServiceProvider): ocho o más,
        // con letras y números en producción. Antes sólo miraba el largo, así
        // que "12345678" pasaba justo para la cuenta del dueño.
        $validador = Validator::make(['clave' => $clave], ['clave' => ['required', Password::defaults()]]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $usuario->forceFill(['password' => Hash::make((string) $clave)])->save();

        $this->info("Listo. {$usuario->email} ({$usuario->role}) ya puede entrar con la contraseña nueva.");

        return self::SUCCESS;
    }
}
