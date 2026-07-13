<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Categoria;
use App\Models\Combo;
use App\Models\PedidoItem;
use App\Models\Producto;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function __invoke()
    {
        $banners = Banner::activos()->get()->map(fn ($b) => [
            'id' => $b->id,
            'imagen' => $b->imagen_url,
            'url' => $b->url,
            'destino_tipo' => $b->destino_tipo,
        ]);

        $pasillos = Categoria::activos()
            ->has('productos')
            ->withCount(['productos' => fn ($q) => $q->activos()])
            ->orderBy('orden')
            ->get()
            ->map(function ($cat) {
                $productos = Producto::activos()
                    ->where('categoria_id', $cat->id)
                    ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()])
                    ->orderBy('nombre')
                    ->take(12)
                    ->get();

                return [
                    'id' => $cat->id,
                    'nombre' => $cat->nombre,
                    'slug' => $cat->slug,
                    'total' => $cat->productos_count,
                    'productos' => $productos,
                ];
            })
            ->filter(fn ($p) => $p['productos']->isNotEmpty())
            ->values();

        $combos = Combo::activos()
            ->with(['items.presentacion.producto'])
            ->take(6)->get()
            ->each(function ($combo) {
                $combo->precio_final = $combo->precio;
                $combo->precio_sin_descuento = $combo->precio_calculado;
            });

        $topProductoIds = PedidoItem::join('presentaciones', 'pedido_items.presentacion_id', '=', 'presentaciones.id')
            ->selectRaw('presentaciones.producto_id, SUM(pedido_items.cantidad) as total_vendido')
            ->groupBy('presentaciones.producto_id')
            ->orderByDesc('total_vendido')
            ->take(12)
            ->pluck('producto_id');

        $masVendidos = $topProductoIds->isNotEmpty()
            ? Producto::activos()
                ->whereIn('id', $topProductoIds)
                ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()])
                ->get()
                ->sortBy(fn ($p) => $topProductoIds->search($p->id))
                ->values()
            : collect();

        return Inertia::render('Home', [
            'banners' => $banners,
            'pasillos' => $pasillos,
            'combos' => $combos,
            'masVendidos' => $masVendidos,
        ]);
    }
}
