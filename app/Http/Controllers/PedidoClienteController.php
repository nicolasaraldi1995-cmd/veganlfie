<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use App\Models\Producto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PedidoClienteController extends Controller
{
    public function pdf(Pedido $pedido)
    {
        $this->authorize('view', $pedido);
        $pedido->load(['items.presentacion.producto.marca']);

        return Pdf::loadView('pdf.pedido', compact('pedido'))
            ->download("pedido-{$pedido->id}.pdf");
    }

    public function show(Pedido $pedido)
    {
        $this->authorize('view', $pedido);

        $pedido->load(['items.presentacion.producto.marca', 'items.presentacion.producto.categoria']);

        $categoriaIds = $pedido->items->pluck('presentacion.producto.categoria_id')->unique();
        $presentacionIds = $pedido->items->pluck('presentacion_id');

        $recomendados = Producto::activos()
            ->whereIn('categoria_id', $categoriaIds)
            ->whereDoesntHave('presentaciones', fn ($q) => $q->whereIn('id', $presentacionIds))
            ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()])
            ->inRandomOrder()
            ->take(8)
            ->get();

        return Inertia::render('Pedido/Show', [
            'pedido' => $pedido,
            'recomendados' => $recomendados,
        ]);
    }

    public function updateItem(Request $request, Pedido $pedido)
    {
        $this->authorize('update', $pedido);
        if (! $pedido->esEditable()) {
            return back()->withErrors(['pedido' => 'Este pedido ya no se puede modificar.']);
        }

        $request->validate([
            'presentacion_id' => 'required|exists:presentaciones,id',
            // Mismo tope que el carrito (ver UpdateCartRequest): sin esto, con el
            // control de stock apagado entraba cualquier numero y reventaba la columna.
            'cantidad' => 'required|integer|min:0|max:9999',
        ]);

        try {
            DB::transaction(function () use ($request, $pedido) {
                $item = $pedido->items()->where('presentacion_id', $request->presentacion_id)->first();

                if ($request->cantidad <= 0) {
                    $item?->delete();
                } elseif ($item) {
                    // No se reprecifica: el pedido se tomó a este precio y ese
                    // número es un hecho. Antes se reponía el precio de HOY, así
                    // que el cliente podía esperar a que bajara o entrara una
                    // oferta y reprecificar su propio pedido para abajo con un
                    // PATCH. Sólo cambia la cantidad; el subtotal se recalcula
                    // sobre el precio que ya tenía el renglón.
                    $item->update([
                        'cantidad' => $request->cantidad,
                        'subtotal' => round((float) $item->precio_unitario * $request->cantidad, 2),
                    ]);
                }

                $pedido->recalcularTotal();
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back();
    }

    public function addItem(Request $request, Pedido $pedido)
    {
        $this->authorize('update', $pedido);
        if (! $pedido->esEditable()) {
            return back()->withErrors(['pedido' => 'Este pedido ya no se puede modificar.']);
        }

        $request->validate([
            'presentacion_id' => 'required|exists:presentaciones,id',
            'cantidad' => 'required|integer|min:1|max:9999',
        ]);

        // El mismo corte que el carrito y la ficha (Presentacion::scopeActivos):
        // producto publicado, presentación activa y con precio. exists no lo
        // mira, así que con un POST directo entraba cualquier presentación de la
        // base -- una apagada, una de un producto de baja, o una a $0, que se
        // pedía gratis.
        $presentacion = Presentacion::activos()->find($request->presentacion_id);

        if (! $presentacion) {
            return back()->withErrors(['presentacion_id' => 'Ese producto ya no está disponible.']);
        }

        try {
            DB::transaction(function () use ($request, $pedido, $presentacion) {
                $precio = $presentacion->precio_final;

                $existing = $pedido->items()->where('presentacion_id', $request->presentacion_id)->first();

                if ($existing) {
                    $newQty = $existing->cantidad + $request->cantidad;
                    $existing->update([
                        'cantidad' => $newQty,
                        'precio_unitario' => $precio,
                        'subtotal' => round($precio * $newQty, 2),
                    ]);
                } else {
                    PedidoItem::create([
                        'pedido_id' => $pedido->id,
                        'presentacion_id' => $request->presentacion_id,
                        'cantidad' => $request->cantidad,
                        'precio_unitario' => $precio,
                        'subtotal' => round($precio * $request->cantidad, 2),
                    ]);
                }

                $pedido->recalcularTotal();
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back();
    }

    public function removeItem(Request $request, Pedido $pedido)
    {
        $this->authorize('update', $pedido);
        if (! $pedido->esEditable()) {
            return back()->withErrors(['pedido' => 'Este pedido ya no se puede modificar.']);
        }

        DB::transaction(function () use ($request, $pedido) {
            // Se borra vía instancia (no ->where()->delete()) para que PedidoItemObserver
            // dispare el evento "deleted" y restaure el stock reservado.
            $pedido->items()->where('presentacion_id', $request->presentacion_id)->first()?->delete();
            $pedido->recalcularTotal();
        });

        return back();
    }
}
