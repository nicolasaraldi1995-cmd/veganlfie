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
            // La franja toma la forma de la imagen: no hay medida que respetar.
            'ancho' => $b->ancho,
            'alto' => $b->alto,
            'url' => $b->url,
            'destino_tipo' => $b->destino_tipo,
        ]);

        $categorias = Categoria::activos()
            ->whereHas('productos', fn ($q) => $q->where('activo', true))
            ->withCount(['productos' => fn ($q) => $q->activos()])
            ->orderBy('orden')
            ->get();

        // Los productos de todas las categorías se traen de una sola vez. Antes
        // acá había un map() que consultaba adentro del bucle: 276 consultas y
        // 1,1 segundos para dibujar la portada, cuatro por cada una de las 66
        // categorías. Se piden juntos y se reparten en PHP, que sale mucho más
        // barato que 66 idas y vueltas a la base.
        $porCategoria = Producto::activos()
            ->whereIn('categoria_id', $categorias->pluck('id'))
            ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()])
            ->orderBy('nombre')
            ->get()
            ->groupBy('categoria_id');

        $pasillos = $categorias
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'nombre' => $cat->nombre,
                'slug' => $cat->slug,
                'total' => $cat->productos_count,
                // El tope de doce queda acá: el pasillo muestra hasta doce y el
                // resto se ve entrando a la categoría.
                'productos' => ($porCategoria[$cat->id] ?? collect())->take(12)->values(),
            ])
            ->filter(fn ($p) => $p['productos']->isNotEmpty())
            ->values();

        // Precios de combo armados a mano: mismo corte para invitados que en
        // Presentacion::toArray.
        $mostrarPrecios = auth()->check();

        $combos = Combo::activos()
            ->with(['items.presentacion.producto.marca'])
            ->take(6)->get()
            ->each(function ($combo) use ($mostrarPrecios) {
                $combo->precio_final = $mostrarPrecios ? $combo->precio : null;
                $combo->precio_sin_descuento = $mostrarPrecios ? $combo->precio_calculado : null;
            });

        // Sólo lo que se vendió de verdad: sin el join a pedidos contaba también
        // los pedidos cancelados y los borrados, así que un pedido grande que se
        // canceló podía trepar un producto a los "más vendidos" de la portada.
        $topProductoIds = PedidoItem::join('presentaciones', 'pedido_items.presentacion_id', '=', 'presentaciones.id')
            ->join('pedidos', 'pedido_items.pedido_id', '=', 'pedidos.id')
            ->whereNull('pedidos.deleted_at')
            ->where('pedidos.estado', '!=', 'canceled')
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
            'mostrarGuiaBienvenida' => session()->pull('mostrar_guia_bienvenida', false),
        ]);
    }
}
