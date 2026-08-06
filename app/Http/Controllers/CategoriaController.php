<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use Inertia\Inertia;

class CategoriaController extends Controller
{
    public function show(Categoria $categoria)
    {
        // Igual que marca y producto: de baja es de baja, también por la URL.
        abort_unless($categoria->activo, 404);

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
