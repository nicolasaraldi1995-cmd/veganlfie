<?php

namespace App\Http\Middleware;

use App\Models\Presentacion;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $cart = session('cart', []);
        $cartItems = [];
        $cartTotal = 0;

        if (! empty($cart)) {
            $presentaciones = Presentacion::with(['producto.marca', 'producto.categoria'])
                ->whereIn('id', array_keys($cart))
                ->get();

            foreach ($presentaciones as $p) {
                $cantidad = $cart[(string) $p->id];
                $precio = $p->precio_final;
                $subtotal = round($precio * $cantidad, 2);
                $cartTotal += $subtotal;

                $cartItems[] = [
                    'presentacion_id' => $p->id,
                    'nombre' => $p->producto->nombre,
                    'marca' => $p->producto->marca->nombre,
                    'categoria' => $p->producto->categoria->nombre,
                    'unidad' => $p->unidad,
                    'precio' => $precio,
                    'precio_original' => (float) $p->precio,
                    'en_oferta' => $p->estaEnOferta(),
                    'cantidad' => $cantidad,
                    'subtotal' => $subtotal,
                    'stock' => $p->stock,
                    'frio' => (bool) $p->producto->frio,
                    'congelado' => (bool) $p->producto->congelado,
                ];
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'cartCount' => array_sum($cart),
            'cartItems' => $cartItems,
            'cartTotal' => round($cartTotal, 2),
        ];
    }
}
