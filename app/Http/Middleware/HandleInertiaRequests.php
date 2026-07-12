<?php

namespace App\Http\Middleware;

use App\Services\CartService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(private CartService $cartService) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $cart = session('cart', []);
        $cartItems = $this->cartService->resolveItems($cart);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'cartCount' => collect($cartItems)->sum('cantidad'),
            'cartItems' => $cartItems,
            'cartTotal' => collect($cartItems)->sum('subtotal'),
        ];
    }
}
