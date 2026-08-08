<?php

namespace App\Observers;

use App\Mail\PedidoEstadoMail;
use App\Models\Pedido;
use Illuminate\Support\Facades\Mail;

/**
 * Todo lo que tiene que pasar cuando un pedido cambia de estado, sin importar
 * por dónde se lo cambió: la acción de la tabla, el botón de adentro del
 * pedido, o el desplegable de estado del formulario.
 *
 * Antes esto vivía sólo en la acción de la tabla. El desplegable de estado no
 * pasaba por ahí: cambiaba la palabra y nada más. Cancelar por el desplegable
 * no devolvía el stock, y confirmar no mandaba el mail que el botón promete.
 * Y revivir un pedido cancelado no volvía a reservar, así que cancelar dos
 * veces inventaba mercadería.
 */
class PedidoObserver
{
    public function updated(Pedido $pedido): void
    {
        if (! $pedido->wasChanged('estado')) {
            return;
        }

        $antes = (string) $pedido->getOriginal('estado');
        $ahora = (string) $pedido->estado;

        // El stock está reservado mientras el pedido no esté cancelado. Cruzar
        // esa línea en un sentido lo devuelve; en el otro lo vuelve a reservar.
        // Cualquier otra transición (pending → confirmed, etc.) no lo toca.
        if ($ahora === 'canceled' && $antes !== 'canceled') {
            $pedido->restaurarStock();
        } elseif ($antes === 'canceled' && $ahora !== 'canceled') {
            $pedido->reservarStock();
        }

        $this->avisarAlCliente($pedido, $ahora);
    }

    private function avisarAlCliente(Pedido $pedido, string $estado): void
    {
        $email = $pedido->datos_cliente['email'] ?? $pedido->user?->email;

        if (! $email) {
            return;
        }

        // Que el mail no salga no puede tumbar el cambio de estado, que ya está
        // guardado. El envío real depende de que el correo esté configurado en
        // el servidor (ver README).
        try {
            Mail::to($email)->send(new PedidoEstadoMail($pedido, $estado));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
