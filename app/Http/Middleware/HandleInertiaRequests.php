<?php

namespace App\Http\Middleware;

use App\Models\Configuracion;
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
        $configuracion = Configuracion::actual();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                // Los precios son solo para clientes con cuenta: la interfaz
                // necesita saberlo para mostrar el aviso en vez del precio.
                'puedeVerPrecios' => $request->user() !== null,
            ],
            // Liviano a propósito (nada de DB): el carrito completo con imagen/marca/
            // categoría se resuelve una sola vez, en CartController::index (/carrito).
            'cartCount' => array_sum($cart),
            'cartPresentacionIds' => array_map('intval', array_keys($cart)),
            'envioGratisDesde' => (float) $configuracion->envio_gratis_desde,
            'controlarStock' => (bool) $configuracion->controlar_stock,
        ];
    }
}
