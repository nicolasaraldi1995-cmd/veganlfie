<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use Inertia\Inertia;

class ComboController extends Controller
{
    public function index()
    {
        $combos = Combo::activos()
            ->with(['items.presentacion.producto'])
            ->paginate(12);

        $combos->getCollection()->transform(function ($combo) {
            $combo->precio_final = $combo->precio;
            $combo->precio_sin_descuento = $combo->precio_calculado;

            return $combo;
        });

        return Inertia::render('Combos/Index', [
            'combos' => $combos,
        ]);
    }
}
