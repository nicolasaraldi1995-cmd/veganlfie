<?php

namespace App\Services;

use App\Models\Presentacion;
use Illuminate\Validation\ValidationException;

class CartService
{
    /**
     * Resolves a session cart (presentacion_id => cantidad) into display-ready line items.
     */
    public function resolveItems(array $cart): array
    {
        if (empty($cart)) {
            return [];
        }

        // whereHas('producto') descarta presentaciones huérfanas (su producto fue
        // borrado): mejor que desaparezcan silenciosamente del carrito a que rompan
        // la página, ya que este resolver corre en cada request (ver HandleInertiaRequests).
        $presentaciones = Presentacion::with(['producto.marca', 'producto.categoria'])
            ->whereIn('id', array_keys($cart))
            ->whereHas('producto')
            ->get();

        return $presentaciones->map(function (Presentacion $p) use ($cart) {
            $cantidad = $cart[(string) $p->id];
            $precio = $p->precio_final;

            $imagen = $p->imagen_url ?? $p->producto->imagen_url;

            return [
                'presentacion_id' => $p->id,
                'nombre' => $p->producto->nombre,
                'marca' => $p->producto->marca?->nombre ?? 'Sin marca',
                'categoria' => $p->producto->categoria?->nombre ?? 'Sin categoría',
                'imagen' => $imagen,
                'unidad' => $p->unidad,
                'precio' => $precio,
                'precio_original' => (float) $p->precio,
                'en_oferta' => $p->estaEnOferta(),
                'cantidad' => $cantidad,
                'subtotal' => round($precio * $cantidad, 2),
                'stock' => $p->stock,
                'frio' => (bool) $p->producto->frio,
                'congelado' => (bool) $p->producto->congelado,
            ];
        })->values()->toArray();
    }

    public function total(array $cart): float
    {
        return collect($this->resolveItems($cart))->sum('subtotal');
    }

    /**
     * Throws a validation error if the desired quantity exceeds available stock.
     * Used when the customer is still building the cart (before an order exists).
     */
    public function assertStockDisponible(int $presentacionId, int $cantidadDeseada): void
    {
        $stock = Presentacion::whereKey($presentacionId)->value('stock') ?? 0;

        if ($cantidadDeseada > $stock) {
            throw ValidationException::withMessages([
                'cantidad' => $stock > 0
                    ? "Solo quedan {$stock} unidades disponibles."
                    : 'Este producto no tiene stock disponible.',
            ]);
        }
    }
}
