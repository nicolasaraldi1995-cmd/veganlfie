<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Inertia\Inertia;

class CategoriaController extends Controller
{
    public function show(Categoria $categoria)
    {
        $productos = $categoria->productos()
            ->activos()
            ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()])
            ->orderBy('nombre')
            ->paginate(24);

        return Inertia::render('Categorias/Show', [
            'categoria' => $categoria,
            'productos' => $productos,
        ]);
    }
}
