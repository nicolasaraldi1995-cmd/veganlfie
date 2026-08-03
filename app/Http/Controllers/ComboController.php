<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use Inertia\Inertia;

class ComboController extends Controller
{
    public function index()
    {
        $combos = Combo::activos()
            ->with(['items.presentacion.producto.marca'])
            ->paginate(12);

        // Los precios de combo se arman a mano acá, así que necesitan su propio
        // corte para invitados (igual criterio que Presentacion::toArray).
        $mostrarPrecios = auth()->check();

        $combos->getCollection()->transform(function ($combo) use ($mostrarPrecios) {
            $combo->precio_final = $mostrarPrecios ? $combo->precio : null;
            $combo->precio_sin_descuento = $mostrarPrecios ? $combo->precio_calculado : null;

            return $combo;
        });

        return Inertia::render('Combos/Index', [
            'combos' => $combos,
        ]);
    }
}
