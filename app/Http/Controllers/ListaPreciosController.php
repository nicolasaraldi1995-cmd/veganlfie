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

    /**
     * La lista completa como planilla, con las mismas columnas que espera el
     * importador: se exporta, se editan los precios en Excel y se vuelve a
     * subir. Reemplaza al archivo del sistema viejo, que era una tabla HTML
     * disfrazada de .xls.
     *
     * Sale en CSV con punto y coma y BOM, que es lo que abre bien el Excel en
     * español sin tener que elegir nada al abrirlo.
     */
    public function planilla()
    {
        $productos = \App\Models\Producto::activos()
            ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()->orderBy('unidad')])
            ->orderBy('nombre')
            ->get();

        $nombre = 'lista-precios-veganlife-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($productos) {
            $salida = fopen('php://output', 'w');

            // BOM: sin esto Excel muestra mal los acentos.
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Nombre', 'Marca', 'Categoría', 'Unidad', 'Precio', 'Stock'], ';');

            foreach ($productos as $producto) {
                foreach ($producto->presentaciones as $presentacion) {
                    fputcsv($salida, [
                        $producto->nombre,
                        $producto->marca->nombre ?? '',
                        $producto->categoria->nombre ?? '',
                        $presentacion->unidad,
                        number_format((float) $presentacion->precio, 2, ',', '.'),
                        $presentacion->stock,
                    ], ';');
                }
            }

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Lista de precios como un HTML autocontenido (estilos, script y logo
     * embebidos) pensada para mandar por WhatsApp: el cliente la abre en el
     * celular y funciona sin internet, con buscador y marcas desplegables.
     */
    public function html()
    {
        ini_set('memory_limit', '512M');
        set_time_limit(90);

        $marcas = Marca::activos()
            ->has('productos')
            ->with(['productos' => fn ($q) => $q->activos()
                ->with(['categoria', 'presentaciones' => fn ($p) => $p->activos()->orderBy('precio')])
                ->orderBy('nombre'),
            ])
            ->orderBy('nombre')
            ->get()
            ->filter(fn ($m) => $m->productos->isNotEmpty())
            ->map(fn ($m) => [
                'nombre' => $m->nombre,
                'inicial' => mb_strtoupper(mb_substr($m->nombre, 0, 1)),
                'productos' => $m->productos
                    ->filter(fn ($p) => $p->presentaciones->isNotEmpty())
                    ->map(fn ($p) => [
                        'nombre' => $p->nombre,
                        'categoria' => $p->categoria?->nombre ?? 'Sin categoría',
                        'sin_tacc' => (bool) $p->sin_tacc,
                        'frio' => (bool) $p->frio,
                        'congelado' => (bool) $p->congelado,
                        'presentaciones' => $p->presentaciones->map(fn ($pr) => [
                            // El id viaja en el archivo del pedido para que al
                            // cargarlo el cruce sea exacto y no por nombre.
                            'id' => $pr->id,
                            'unidad' => $pr->unidad,
                            'precio' => (float) $pr->precio,
                            'precio_final' => $pr->precio_final,
                            'en_oferta' => $pr->estaEnOferta(),
                        ])->values()->all(),
                    ])->values()->all(),
            ])
            ->filter(fn ($m) => $m['productos'] !== [])
            ->values();

        $logo = base64_encode((string) file_get_contents(public_path('images/logo.png')));

        $html = view('lista-precios-html', [
            'marcas' => $marcas,
            'logo' => $logo,
            'totalProductos' => $marcas->sum(fn ($m) => count($m['productos'])),
            'generado' => now(),
        ])->render();

        // Se devuelve como descarga en streaming a propósito: Livewire inyecta
        // su <script src="/livewire/livewire.js"> en cualquier respuesta HTML
        // común, y ese archivo no existe en el celular del cliente -- rompía
        // justamente lo que hace útil a esta lista, que ande sin internet.
        return response()->streamDownload(
            fn () => print($html),
            'VEGANLIFE-precios-'.now()->format('d-m-Y').'.html',
            ['Content-Type' => 'text/html; charset=utf-8'],
        );
    }

    public function pdf()
    {
        // ponytail: ~2000 productos tarda ~28s y ~320MB en dompdf, por encima
        // de los límites default de PHP. Techo: si el catálogo crece bastante
        // más, esto deja de alcanzar y hay que sacar la generación del request
        // (job en cola + aviso cuando esté listo).
        ini_set('memory_limit', '512M');
        set_time_limit(90);

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
