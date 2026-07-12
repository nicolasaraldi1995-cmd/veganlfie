<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $vista = $request->input('vista');
        $marcas = Marca::activos()->orderBy('nombre')->get();
        $categorias = Categoria::activos()->orderBy('orden')->get();
        $filtros = $request->only(['marca', 'categoria', 'sin_tacc', 'frio', 'congelado', 'buscar', 'vista']);

        // --- SEARCH MODE: grouped by category ---
        if ($request->filled('buscar')) {
            $term = $request->buscar;
            $query = Producto::activos()
                ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()])
                ->where(function ($q) use ($term) {
                    $q->where('nombre', 'like', "%{$term}%")
                        ->orWhereHas('marca', fn ($m) => $m->where('nombre', 'like', "%{$term}%"))
                        ->orWhereHas('categoria', fn ($c) => $c->where('nombre', 'like', "%{$term}%"));
                });

            if ($request->boolean('sin_tacc')) {
                $query->sinTacc();
            }
            if ($request->boolean('frio')) {
                $query->where('frio', true);
            }
            if ($request->boolean('congelado')) {
                $query->congelados();
            }

            $productos = $query->orderBy('nombre')->get();
            $porCategoria = $productos->groupBy(fn ($p) => $p->categoria->nombre)
                ->sortKeys()
                ->map(fn ($items, $cat) => ['nombre' => $cat, 'productos' => $items->values()])
                ->values();

            return Inertia::render('Productos/Index', [
                'modo' => 'busqueda',
                'productosPorCategoria' => $porCategoria,
                'totalResultados' => $productos->count(),
                'productos' => null,
                'items' => null,
                'breadcrumb' => null,
                'marcas' => $marcas,
                'categorias' => $categorias,
                'filtros' => $filtros,
            ]);
        }

        // --- CATEGORIAS MODE ---
        if ($vista === 'categorias') {
            if ($request->filled('categoria') && $request->filled('marca')) {
                $cat = Categoria::findOrFail($request->categoria);
                $marca = Marca::findOrFail($request->marca);
                $productos = Producto::activos()
                    ->where('categoria_id', $cat->id)
                    ->where('marca_id', $marca->id)
                    ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()])
                    ->orderBy('nombre')
                    ->paginate(24)->withQueryString();

                return Inertia::render('Productos/Index', [
                    'modo' => 'productos',
                    'productos' => $productos,
                    'breadcrumb' => [
                        ['label' => 'Categorías', 'url' => route('productos.index', ['vista' => 'categorias'])],
                        ['label' => $cat->nombre, 'url' => route('productos.index', ['vista' => 'categorias', 'categoria' => $cat->id])],
                        ['label' => $marca->nombre, 'url' => null],
                    ],
                    'productosPorCategoria' => null,
                    'totalResultados' => null,
                    'items' => null,
                    'marcas' => $marcas,
                    'categorias' => $categorias,
                    'filtros' => $filtros,
                ]);
            }

            if ($request->filled('categoria')) {
                $cat = Categoria::findOrFail($request->categoria);
                $marcasEnCategoria = Marca::activos()
                    ->whereHas('productos', fn ($q) => $q->activos()->where('categoria_id', $cat->id))
                    ->withCount(['productos' => fn ($q) => $q->activos()->where('categoria_id', $cat->id)])
                    ->orderBy('nombre')
                    ->get();

                return Inertia::render('Productos/Index', [
                    'modo' => 'marcas_en_categoria',
                    'items' => $marcasEnCategoria,
                    'breadcrumb' => [
                        ['label' => 'Categorías', 'url' => route('productos.index', ['vista' => 'categorias'])],
                        ['label' => $cat->nombre, 'url' => null],
                    ],
                    'categoriaActual' => $cat,
                    'productos' => null,
                    'productosPorCategoria' => null,
                    'totalResultados' => null,
                    'marcas' => $marcas,
                    'categorias' => $categorias,
                    'filtros' => $filtros,
                ]);
            }

            $categoriasConCount = Categoria::activos()
                ->has('productos')
                ->withCount(['productos' => fn ($q) => $q->activos()])
                ->orderBy('orden')
                ->get();

            return Inertia::render('Productos/Index', [
                'modo' => 'categorias',
                'items' => $categoriasConCount,
                'breadcrumb' => [['label' => 'Categorías', 'url' => null]],
                'productos' => null,
                'productosPorCategoria' => null,
                'totalResultados' => null,
                'marcas' => $marcas,
                'categorias' => $categorias,
                'filtros' => $filtros,
            ]);
        }

        // --- MARCAS MODE ---
        if ($vista === 'marcas') {
            if ($request->filled('marca') && $request->filled('categoria')) {
                $marca = Marca::findOrFail($request->marca);
                $cat = Categoria::findOrFail($request->categoria);
                $productos = Producto::activos()
                    ->where('marca_id', $marca->id)
                    ->where('categoria_id', $cat->id)
                    ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()])
                    ->orderBy('nombre')
                    ->paginate(24)->withQueryString();

                return Inertia::render('Productos/Index', [
                    'modo' => 'productos',
                    'productos' => $productos,
                    'breadcrumb' => [
                        ['label' => 'Marcas', 'url' => route('productos.index', ['vista' => 'marcas'])],
                        ['label' => $marca->nombre, 'url' => route('productos.index', ['vista' => 'marcas', 'marca' => $marca->id])],
                        ['label' => $cat->nombre, 'url' => null],
                    ],
                    'productosPorCategoria' => null,
                    'totalResultados' => null,
                    'items' => null,
                    'marcas' => $marcas,
                    'categorias' => $categorias,
                    'filtros' => $filtros,
                ]);
            }

            if ($request->filled('marca')) {
                $marca = Marca::findOrFail($request->marca);
                $categoriasEnMarca = Categoria::activos()
                    ->whereHas('productos', fn ($q) => $q->activos()->where('marca_id', $marca->id))
                    ->withCount(['productos' => fn ($q) => $q->activos()->where('marca_id', $marca->id)])
                    ->orderBy('orden')
                    ->get();

                return Inertia::render('Productos/Index', [
                    'modo' => 'categorias_en_marca',
                    'items' => $categoriasEnMarca,
                    'breadcrumb' => [
                        ['label' => 'Marcas', 'url' => route('productos.index', ['vista' => 'marcas'])],
                        ['label' => $marca->nombre, 'url' => null],
                    ],
                    'marcaActual' => $marca,
                    'productos' => null,
                    'productosPorCategoria' => null,
                    'totalResultados' => null,
                    'marcas' => $marcas,
                    'categorias' => $categorias,
                    'filtros' => $filtros,
                ]);
            }

            $marcasConCount = Marca::activos()
                ->has('productos')
                ->withCount(['productos' => fn ($q) => $q->activos()])
                ->orderBy('nombre')
                ->get();

            return Inertia::render('Productos/Index', [
                'modo' => 'marcas',
                'items' => $marcasConCount,
                'breadcrumb' => [['label' => 'Marcas', 'url' => null]],
                'productos' => null,
                'productosPorCategoria' => null,
                'totalResultados' => null,
                'marcas' => $marcas,
                'categorias' => $categorias,
                'filtros' => $filtros,
            ]);
        }

        // --- DEFAULT: flat product listing ---
        $query = Producto::activos()
            ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()]);

        if ($request->filled('marca')) {
            $query->where('marca_id', $request->marca);
        }
        if ($request->filled('categoria')) {
            $query->where('categoria_id', $request->categoria);
        }
        if ($request->boolean('sin_tacc')) {
            $query->sinTacc();
        }
        if ($request->boolean('congelado')) {
            $query->congelados();
        }

        $productos = $query->orderBy('nombre')->paginate(24)->withQueryString();

        return Inertia::render('Productos/Index', [
            'modo' => 'productos',
            'productos' => $productos,
            'breadcrumb' => null,
            'productosPorCategoria' => null,
            'totalResultados' => null,
            'items' => null,
            'marcas' => $marcas,
            'categorias' => $categorias,
            'filtros' => $filtros,
        ]);
    }

    public function show(Producto $producto)
    {
        $producto->load(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()]);

        $relacionados = Producto::activos()
            ->where('categoria_id', $producto->categoria_id)
            ->where('id', '!=', $producto->id)
            ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()])
            ->take(6)->get();

        return Inertia::render('Productos/Show', [
            'producto' => $producto,
            'relacionados' => $relacionados,
        ]);
    }

    public function buscar(Request $request)
    {
        $q = $request->input('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $productos = Producto::activos()
            ->where(function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                    ->orWhereHas('marca', fn ($m) => $m->where('nombre', 'like', "%{$q}%"));
            })
            ->with('marca:id,nombre')
            ->select('id', 'nombre', 'slug', 'marca_id', 'imagen')
            ->take(8)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'marca' => $p->marca->nombre ?? '',
                'slug' => $p->slug,
                'imagen' => $p->imagen ? "/storage/{$p->imagen}" : null,
            ]);

        return response()->json($productos);
    }
}
