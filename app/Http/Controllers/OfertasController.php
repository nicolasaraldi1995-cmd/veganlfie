<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Inertia\Inertia;

class OfertasController extends Controller
{
    public function __invoke()
    {
        $productos = Producto::activos()
            ->whereHas('presentaciones', fn ($q) => $q->activos()->enOferta())
            ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()])
            ->orderBy('nombre')
            ->get();

        $porMarca = $productos->groupBy(fn ($p) => $p->marca?->nombre ?? 'Sin marca')
            ->sortKeys()
            ->map(fn ($items, $marca) => [
                'marca' => $marca,
                'total' => $items->count(),
                'productos' => $items->values(),
            ])
            ->values();

        return Inertia::render('Ofertas', [
            'porMarca' => $porMarca,
            'totalOfertas' => $productos->count(),
        ]);
    }
}
