<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutStoreRequest;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Services\CartService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cartService) {}

    public function index()
    {
        $cart = session('cart', []);
        if (empty($cart)) {
            return redirect()->route('productos.index');
        }

        $items = $this->cartService->resolveItems($cart);
        $total = collect($items)->sum('subtotal');
        $user = auth()->user();

        $cartPresentacionIds = array_keys($cart);
        $cartProductoIds = Presentacion::whereIn('id', $cartPresentacionIds)
            ->pluck('producto_id')
            ->unique();

        $recomendados = collect();

        // Products the client has bought before but aren't in this cart
        $historialProductoIds = PedidoItem::whereHas('pedido', fn ($q) => $q->where('user_id', $user->id))
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

        // Fill remaining slots with products from same categories
        if ($recomendados->count() < 8) {
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

        return Inertia::render('Checkout', [
            'items' => $items,
            'total' => $total,
            'envioGratis' => $total >= 600000,
            'recomendados' => $recomendados,
            'cliente' => [
                'nombre' => $user->name,
                'negocio' => $user->negocio,
                'tipo_cliente' => $user->tipo_cliente,
                'email' => $user->email,
                'celular' => $user->celular,
                'direccion' => $user->direccion,
                'ciudad' => $user->ciudad,
                'provincia' => $user->provincia,
            ],
        ]);
    }

    public function store(CheckoutStoreRequest $request)
    {
        $cart = session('cart', []);
        if (empty($cart)) {
            return redirect()->route('productos.index');
        }

        $items = $this->cartService->resolveItems($cart);
        $total = collect($items)->sum('subtotal');
        $user = auth()->user();

        try {
            $pedido = DB::transaction(function () use ($items, $total, $user, $request) {
                $pedido = Pedido::create([
                    'user_id' => $user->id,
                    'estado' => 'pending',
                    'total' => $total,
                    'datos_cliente' => [
                        'nombre' => $user->name,
                        'negocio' => $user->negocio,
                        'tipo_cliente' => $user->tipo_cliente,
                        'email' => $user->email,
                        'celular' => $user->celular,
                        'direccion' => $user->direccion,
                        'ciudad' => $user->ciudad,
                        'provincia' => $user->provincia,
                        'entrega' => $request->entrega,
                        'notas' => $request->notas,
                    ],
                ]);

                foreach ($items as $item) {
                    // PedidoItemObserver valida y descuenta el stock al crear cada item.
                    PedidoItem::create([
                        'pedido_id' => $pedido->id,
                        'presentacion_id' => $item['presentacion_id'],
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $item['precio'],
                        'subtotal' => $item['subtotal'],
                    ]);
                }

                return $pedido;
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        session()->forget('cart');

        return redirect()->route('checkout.confirmacion', $pedido->id);
    }

    public function confirmacion(Pedido $pedido)
    {
        if ($pedido->user_id !== auth()->id()) {
            abort(403);
        }

        $pedido->load(['items.presentacion.producto.marca', 'items.presentacion.producto.categoria']);

        return Inertia::render('CheckoutConfirmacion', [
            'pedido' => $pedido,
        ]);
    }
}
