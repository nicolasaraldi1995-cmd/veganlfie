<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Inertia\Inertia;

class VeganlifeController extends Controller
{
    public function __invoke()
    {
        $productos = Producto::activos()
            ->whereHas('marca', fn ($q) => $q->where('nombre', 'Veganlife'))
            ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()])
            ->orderBy('nombre')
            ->paginate(24);

        return Inertia::render('Veganlife', [
            'productos' => $productos,
        ]);
    }
}
