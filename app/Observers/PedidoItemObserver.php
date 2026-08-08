<?php

namespace App\Observers;

use App\Models\Configuracion;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use Illuminate\Validation\ValidationException;

/**
 * Keeps Presentacion.stock in sync with pedido items regardless of where the
 * item was created/edited (checkout, cliente autoservicio, o panel Filament).
 */
class PedidoItemObserver
{
    public function creating(PedidoItem $item): void
    {
        $this->ajustar($item->presentacion_id, $item->cantidad);
    }

    public function updating(PedidoItem $item): void
    {
        // Un pedido cancelado ya devolvió su stock: tocarle un renglón no puede
        // volver a moverlo, o el número se infla. Editar los items de un pedido
        // cancelado no debería pasar, pero el panel lo permite.
        if ($this->pedidoCancelado($item)) {
            return;
        }

        // Cambiar el producto de un renglón ya cargado (se puede desde el panel)
        // mueve la reserva entera: se le devuelve al producto anterior y se le
        // descuenta al nuevo. Sin esto, el stock viejo quedaba reservado para
        // siempre y al nuevo no se le descontaba nada.
        if ($item->isDirty('presentacion_id')) {
            $this->ajustar((int) $item->getOriginal('presentacion_id'), -((int) $item->getOriginal('cantidad')));
            $this->ajustar($item->presentacion_id, $item->cantidad);

            return;
        }

        if (! $item->isDirty('cantidad')) {
            return;
        }

        $delta = $item->cantidad - $item->getOriginal('cantidad');
        $this->ajustar($item->presentacion_id, $delta);
    }

    public function deleted(PedidoItem $item): void
    {
        if ($this->pedidoCancelado($item)) {
            return;
        }

        $this->ajustar($item->presentacion_id, -$item->cantidad);
    }

    /**
     * El stock de un pedido cancelado ya se devolvió al cancelarlo, así que sus
     * renglones no deben mover más el stock.
     */
    private function pedidoCancelado(PedidoItem $item): bool
    {
        return $item->pedido?->estado === 'canceled';
    }

    /**
     * Positive $delta reserves stock (decrements), negative $delta releases it (increments).
     * Requires the caller to run inside DB::transaction() for the row lock to be effective.
     */
    private function ajustar(int $presentacionId, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        $presentacion = Presentacion::whereKey($presentacionId)->lockForUpdate()->first();

        if (! $presentacion) {
            return;
        }

        if ($delta > 0 && $delta > $presentacion->stock && Configuracion::actual()->controlar_stock) {
            throw ValidationException::withMessages([
                // Sin el número, por lo mismo que en CartService::assertStockDisponible.
                'cantidad' => "No nos queda esa cantidad de {$presentacion->unidad}. Probá pidiendo menos.",
            ]);
        }

        $presentacion->decrement('stock', $delta);
    }
}
