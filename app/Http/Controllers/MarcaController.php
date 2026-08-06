<?php

namespace App\Http\Controllers;

use App\Models\Marca;
use Inertia\Inertia;

class MarcaController extends Controller
{
    public function show(Marca $marca)
    {
        // Mismo criterio que la ficha de producto: una marca dada de baja no
        // sale en ningún listado, pero su página respondía igual por el link
        // directo.
        abort_unless($marca->activo, 404);

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
