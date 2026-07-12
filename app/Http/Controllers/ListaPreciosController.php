<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Marca;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;

class ListaPreciosController extends Controller
{
    public function index()
    {
        $categorias = Categoria::activos()
            ->has('productos')
            ->with(['productos' => fn ($q) => $q->activos()
                ->with(['marca', 'presentaciones' => fn ($p) => $p->activos()->orderBy('precio')])
                ->orderBy('nombre'),
            ])
            ->orderBy('nombre')
            ->get()
            ->filter(fn ($c) => $c->productos->isNotEmpty())
            ->map(fn ($c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'productos' => $c->productos->map(fn ($p) => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'marca' => $p->marca->nombre ?? '—',
                    'sin_tacc' => $p->sin_tacc,
                    'frio' => $p->frio,
                    'congelado' => $p->congelado,
                    'presentaciones' => $p->presentaciones->map(fn ($pr) => [
                        'unidad' => $pr->unidad,
                        'precio' => (float) $pr->precio,
                        'precio_final' => $pr->precio_final,
                        'en_oferta' => $pr->estaEnOferta(),
                        'stock' => $pr->stock,
                    ]),
                ]),
            ])
            ->values();

        $marcas = Marca::activos()->orderBy('nombre')->pluck('nombre')->values();

        return Inertia::render('ListaPrecios', [
            'categorias' => $categorias,
            'marcas' => $marcas,
        ]);
    }

    public function pdf()
    {
        $categorias = Categoria::activos()
            ->has('productos')
            ->with(['productos' => fn ($q) => $q->activos()
                ->with(['marca', 'presentaciones' => fn ($p) => $p->activos()->orderBy('precio')])
                ->orderBy('nombre'),
            ])
            ->orderBy('nombre')
            ->get()
            ->filter(fn ($c) => $c->productos->isNotEmpty());

        $totalProductos = $categorias->sum(fn ($c) => $c->productos->count());
        $totalPresentaciones = $categorias->sum(fn ($c) => $c->productos->sum(fn ($p) => $p->presentaciones->count()));

        return Pdf::loadView('pdf.lista-precios', compact('categorias', 'totalProductos', 'totalPresentaciones'))
            ->setPaper('a4', 'portrait')
            ->download('VEGANLIFE-lista-precios-'.now()->format('Y-m-d').'.pdf');
    }
}
