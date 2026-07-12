<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use Inertia\Inertia;

class MarcaController extends Controller
{
    public function show(Marca $marca)
    {
        $productos = $marca->productos()
            ->activos()
            ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()])
            ->orderBy('nombre')
            ->paginate(24);

        return Inertia::render('Marcas/Show', [
            'marca' => $marca,
            'productos' => $productos,
        ]);
    }
}
