<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Para saber con qué correo entrar al panel cuando no se recuerda. El panel no
 * tiene "olvidé mi contraseña" y el envío de mails todavía no está configurado.
 */
class ListarUsuarios extends Command
{
    protected $signature = 'usuarios:listar {--rol= : admin, operador o cliente}';

    protected $description = 'Lista las cuentas y su rol (no muestra contraseñas)';

    public function handle(): int
    {
        $usuarios = User::query()
            ->when($this->option('rol'), fn ($q, $rol) => $q->where('role', $rol))
            ->orderBy('role')
            ->orderBy('email')
            ->get(['id', 'name', 'email', 'role', 'created_at']);

        if ($usuarios->isEmpty()) {
            $this->warn('No hay cuentas que coincidan.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Nombre', 'Correo', 'Rol', 'Creada'],
            $usuarios->map(fn (User $u) => [
                $u->id,
                $u->name,
                $u->email,
                $u->role,
                $u->created_at?->format('d/m/Y'),
            ])->all()
        );

        return self::SUCCESS;
    }
}
