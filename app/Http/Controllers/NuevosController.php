<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Inertia\Inertia;

class NuevosController extends Controller
{
    public function __invoke()
    {
        $productos = Producto::activos()->nuevos()
            ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()])
            ->orderBy('created_at', 'desc')
            ->paginate(24);

        return Inertia::render('Nuevos', [
            'productos' => $productos,
        ]);
    }
}
