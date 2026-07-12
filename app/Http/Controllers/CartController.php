<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\AddComboToCartRequest;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\RemoveFromCartRequest;
use App\Http\Requests\Cart\UpdateCartRequest;
use App\Models\Combo;
use App\Services\CartService;
use Inertia\Inertia;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    public function index()
    {
        $cart = session('cart', []);
        $items = $this->cartService->resolveItems($cart);

        return Inertia::render('Cart', [
            'items' => $items,
            'total' => collect($items)->sum('subtotal'),
        ]);
    }

    public function add(AddToCartRequest $request)
    {
        $cart = session('cart', []);
        $id = (string) $request->presentacion_id;
        $nuevaCantidad = ($cart[$id] ?? 0) + $request->cantidad;

        $this->cartService->assertStockDisponible($request->presentacion_id, $nuevaCantidad);

        $cart[$id] = $nuevaCantidad;
        session(['cart' => $cart]);

        return back();
    }

    public function update(UpdateCartRequest $request)
    {
        $cart = session('cart', []);
        $id = (string) $request->presentacion_id;

        if ($request->cantidad <= 0) {
            unset($cart[$id]);
        } else {
            $this->cartService->assertStockDisponible($request->presentacion_id, $request->cantidad);
            $cart[$id] = $request->cantidad;
        }

        session(['cart' => $cart]);

        return back();
    }

    public function addCombo(AddComboToCartRequest $request)
    {
        $combo = Combo::with('items.presentacion')->findOrFail($request->combo_id);
        $cart = session('cart', []);

        foreach ($combo->items as $item) {
            $id = (string) $item->presentacion_id;
            $nuevaCantidad = ($cart[$id] ?? 0) + $item->cantidad;
            $this->cartService->assertStockDisponible($item->presentacion_id, $nuevaCantidad);
            $cart[$id] = $nuevaCantidad;
        }

        session(['cart' => $cart]);

        return back();
    }

    public function remove(RemoveFromCartRequest $request)
    {
        $cart = session('cart', []);
        unset($cart[(string) $request->presentacion_id]);
        session(['cart' => $cart]);

        return back();
    }
}
