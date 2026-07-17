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
        if (! $item->isDirty('cantidad')) {
            return;
        }

        $delta = $item->cantidad - $item->getOriginal('cantidad');
        $this->ajustar($item->presentacion_id, $delta);
    }

    public function deleted(PedidoItem $item): void
    {
        $this->ajustar($item->presentacion_id, -$item->cantidad);
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
                'cantidad' => "Solo quedan {$presentacion->stock} unidades disponibles de {$presentacion->unidad}.",
            ]);
        }

        $presentacion->decrement('stock', $delta);
    }
}
