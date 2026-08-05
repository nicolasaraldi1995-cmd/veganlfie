<?php

namespace App\Policies;

use App\Models\Pedido;
use App\Models\User;

class PedidoPolicy
{
    public function view(User $user, Pedido $pedido): bool
    {
        return $user->id === $pedido->user_id || $user->isAdmin() || $user->isOperador();
    }

    /**
     * El cliente sobre su propio pedido, y el personal sobre cualquiera: el
     * operador prepara, confirma y cancela, que es su trabajo. Antes esto
     * devolvía false para el operador y PedidoResource lo pisaba con un
     * override: la regla decía una cosa y el panel hacía la otra.
     *
     * Ojo, esto habilita a tocar el pedido, no a ponerle precio: los importes
     * se rearman en el servidor (ver PedidoResource::precioDeLaBase).
     */
    public function update(User $user, Pedido $pedido): bool
    {
        return $user->id === $pedido->user_id || $user->isAdmin() || $user->isOperador();
    }
}
