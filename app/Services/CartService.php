<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Collection;
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
                'producto_id' => $p->producto_id,
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
     * Products to suggest alongside the cart/checkout: things the customer bought
     * before that aren't in the current cart, filled out with same-category picks
     * if there isn't enough purchase history to reach 8.
     */
    public function recomendadosPara(?User $user, array $cartPresentacionIds): Collection
    {
        $cartProductoIds = Presentacion::whereIn('id', $cartPresentacionIds)->pluck('producto_id')->unique();

        $recomendados = collect();

        if ($user) {
            $historialProductoIds = PedidoItem::whereHas('pedido', fn ($q) => $q->where('user_id', $user->id)->where('estado', '!=', 'canceled'))
                ->join('presentaciones', 'pedido_items.presentacion_id', '=', 'presentaciones.id')
                ->whereNotIn('presentaciones.producto_id', $cartProductoIds)
                ->select('presentaciones.producto_id')
                ->distinct()
                ->pluck('producto_id');

            if ($historialProductoIds->isNotEmpty()) {
                $recomendados = Producto::activos()
                    ->whereIn('id', $historialProductoIds)
                    ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()])
                    ->inRandomOrder()
                    ->take(8)
                    ->get();
            }
        }

        if ($recomendados->count() < 8 && ! empty($cartPresentacionIds)) {
            $categoriaIds = Presentacion::whereIn('id', $cartPresentacionIds)
                ->with('producto')
                ->get()
                ->pluck('producto.categoria_id')
                ->unique();

            $fill = Producto::activos()
                ->whereIn('categoria_id', $categoriaIds)
                ->whereNotIn('id', $cartProductoIds)
                ->whereNotIn('id', $recomendados->pluck('id'))
                ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()])
                ->inRandomOrder()
                ->take(8 - $recomendados->count())
                ->get();

            $recomendados = $recomendados->concat($fill);
        }

        return $recomendados;
    }

    /**
     * Throws a validation error if the desired quantity exceeds available stock.
     * Used when the customer is still building the cart (before an order exists).
     */
    public function assertStockDisponible(int $presentacionId, int $cantidadDeseada): void
    {
        if (! Configuracion::actual()->controlar_stock) {
            return;
        }

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
