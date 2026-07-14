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
        if ($pedido->user_id !== auth()->id()) {
            abort(403);
        }
        $pedido->load(['items.presentacion.producto.marca']);

        return Pdf::loadView('pdf.pedido', compact('pedido'))
            ->download("pedido-{$pedido->id}.pdf");
    }

    public function show(Pedido $pedido)
    {
        if ($pedido->user_id !== auth()->id()) {
            abort(403);
        }

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
        if ($pedido->user_id !== auth()->id()) {
            abort(403);
        }
        if (! $pedido->esEditable()) {
            return back()->withErrors(['pedido' => 'Este pedido ya no se puede modificar.']);
        }

        $request->validate([
            'presentacion_id' => 'required|exists:presentaciones,id',
            'cantidad' => 'required|integer|min:0',
        ]);

        try {
            DB::transaction(function () use ($request, $pedido) {
                $item = $pedido->items()->where('presentacion_id', $request->presentacion_id)->first();

                if ($request->cantidad <= 0) {
                    $item?->delete();
                } elseif ($item) {
                    $presentacion = Presentacion::find($request->presentacion_id);
                    $precio = $presentacion->precio_final;
                    $item->update([
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

    public function addItem(Request $request, Pedido $pedido)
    {
        if ($pedido->user_id !== auth()->id()) {
            abort(403);
        }
        if (! $pedido->esEditable()) {
            return back()->withErrors(['pedido' => 'Este pedido ya no se puede modificar.']);
        }

        $request->validate([
            'presentacion_id' => 'required|exists:presentaciones,id',
            'cantidad' => 'required|integer|min:1',
        ]);

        try {
            DB::transaction(function () use ($request, $pedido) {
                $presentacion = Presentacion::findOrFail($request->presentacion_id);
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
        if ($pedido->user_id !== auth()->id()) {
            abort(403);
        }
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
