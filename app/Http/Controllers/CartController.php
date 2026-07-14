<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\AddComboToCartRequest;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\RemoveFromCartRequest;
use App\Http\Requests\Cart\UpdateCartRequest;
use App\Models\Combo;
use App\Models\Presentacion;
use App\Services\CartService;
use Inertia\Inertia;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    public function index()
    {
        $cart = session('cart', []);
        $items = $this->cartService->resolveItems($cart);
        $user = auth()->user();

        $recomendados = $this->cartService->recomendadosPara($user, array_keys($cart));

        $pedidoAnterior = null;
        if ($user) {
            $ultimo = $user->pedidos()
                ->whereNotIn('estado', ['canceled', 'draft'])
                ->with('items.presentacion.producto.marca')
                ->latest()
                ->first();

            if ($ultimo) {
                $itemsValidos = $ultimo->items
                    ->filter(fn ($it) => $it->presentacion?->producto)
                    ->map(fn ($it) => [
                        'presentacion_id' => $it->presentacion_id,
                        'producto_id' => $it->presentacion->producto_id,
                        'nombre' => $it->presentacion->producto->nombre,
                        'marca' => $it->presentacion->producto->marca?->nombre ?? 'Sin marca',
                        'unidad' => $it->presentacion->unidad,
                        'imagen' => $it->presentacion->imagen_url ?? $it->presentacion->producto->imagen_url,
                    ])
                    ->values();

                // Si todos los productos del pedido anterior ya no existen, no hay nada
                // real para comparar: mejor ocultar la función que decir "tenés todo".
                if ($itemsValidos->isNotEmpty()) {
                    $pedidoAnterior = [
                        'id' => $ultimo->id,
                        'fecha' => $ultimo->created_at->format('d/m/Y'),
                        'items' => $itemsValidos,
                    ];
                }
            }
        }

        return Inertia::render('Cart', [
            'items' => $items,
            'total' => collect($items)->sum('subtotal'),
            'recomendados' => $recomendados,
            'pedidoAnterior' => $pedidoAnterior,
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

    public function removeFrioCongelado()
    {
        $cart = session('cart', []);

        if (empty($cart)) {
            return back();
        }

        $idsFrioCongelado = Presentacion::with('producto')
            ->whereIn('id', array_keys($cart))
            ->get()
            ->filter(fn ($p) => $p->producto && ($p->producto->frio || $p->producto->congelado))
            ->pluck('id');

        foreach ($idsFrioCongelado as $id) {
            unset($cart[(string) $id]);
        }

        session(['cart' => $cart]);

        return back();
    }
}
