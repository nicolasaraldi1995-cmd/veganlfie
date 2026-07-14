<?php

namespace App\Http\Controllers;

use App\Models\PedidoItem;
use App\Models\Producto;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's account dashboard.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $pedidosRecientes = $user->pedidos()
            ->where('estado', '!=', 'draft')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'estado' => $p->estado,
                'total' => (float) $p->total,
                'fecha' => $p->created_at->format('d/m/Y'),
            ]);

        $totalPedidos = $user->pedidos()->where('estado', '!=', 'draft')->count();

        $topProductoIds = PedidoItem::join('pedidos', 'pedido_items.pedido_id', '=', 'pedidos.id')
            ->join('presentaciones', 'pedido_items.presentacion_id', '=', 'presentaciones.id')
            ->where('pedidos.user_id', $user->id)
            ->where('pedidos.estado', '!=', 'canceled')
            ->selectRaw('presentaciones.producto_id, SUM(pedido_items.cantidad) as total_comprado')
            ->groupBy('presentaciones.producto_id')
            ->orderByDesc('total_comprado')
            ->take(8)
            ->pluck('producto_id');

        $productosFrecuentes = $topProductoIds->isNotEmpty()
            ? Producto::whereIn('id', $topProductoIds)
                ->with('marca')
                ->get()
                ->sortBy(fn ($p) => $topProductoIds->search($p->id))
                ->values()
            : collect();

        return Inertia::render('Profile/Edit', [
            'cliente' => [
                'nombre' => $user->name,
                'negocio' => $user->negocio,
                'email' => $user->email,
                'celular' => $user->celular,
                'direccion' => $user->direccion,
                'ciudad' => $user->ciudad,
                'provincia' => $user->provincia,
            ],
            'totalPedidos' => $totalPedidos,
            'pedidosRecientes' => $pedidosRecientes,
            'productosFrecuentes' => $productosFrecuentes,
        ]);
    }
}
