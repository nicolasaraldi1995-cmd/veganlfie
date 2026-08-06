<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ListaPreciosController extends Controller
{
    public function index()
    {
        $categorias = Categoria::activos()
            ->whereHas('productos', fn ($q) => $q->where('activo', true))
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
     * La lista completa como archivo de Excel de verdad (.xlsx).
     *
     * Sirve para dos cosas a la vez:
     *
     * 1. Pasar pedido al proveedor. Va ordenada por marca y con el filtro
     *    puesto en los encabezados: se elige la marca del proveedor, se cargan
     *    las cantidades en "Cant. a pedir", el total se calcula solo y se le
     *    manda la captura de esa parte. Es lo que se hacía a mano.
     * 2. Corregir precios en masa. Los encabezados son los que reconoce el
     *    Importador, así que se baja, se editan los precios y se vuelve a
     *    subir. "Cant. a pedir" y "Total" no los mira nadie al importar.
     *
     * Antes salía en CSV. El CSV abre en Excel pero no lleva fórmulas, ni
     * filtro, ni encabezado fijo, que es justamente lo que hace falta para
     * cargar un pedido de 2000 renglones.
     */
    public function planilla()
    {
        // Mucho más liviano que el PDF (que anda por los 360MB), pero son 2161
        // presentaciones y el default de PHP son 128M.
        ini_set('memory_limit', '512M');
        set_time_limit(90);

        $productos = Producto::activos()
            ->with(['marca', 'categoria', 'presentaciones' => fn ($q) => $q->activos()->orderBy('unidad')])
            ->get()
            // Por marca y después por nombre: así, al filtrar por el proveedor,
            // sus productos quedan juntos y en orden. Se ordena en PHP y no en
            // la base para no tener que joinear marcas, que también tiene una
            // columna "activo" y volvería ambigua la del scope.
            ->sortBy(fn ($p) => mb_strtolower(($p->marca->nombre ?? 'zzz').'|'.$p->nombre))
            ->values();

        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Lista de precios');

        $hoja->fromArray(self::COLUMNAS, null, 'A1');

        $fila = 2;
        foreach ($productos as $producto) {
            foreach ($producto->presentaciones as $presentacion) {
                self::comoTexto($hoja, 'A'.$fila, $producto->marca->nombre ?? '');
                self::comoTexto($hoja, 'B'.$fila, $producto->nombre);
                self::comoTexto($hoja, 'C'.$fila, $producto->categoria->nombre ?? '');
                self::comoTexto($hoja, 'D'.$fila, $presentacion->unidad);
                $hoja->setCellValue('E'.$fila, (float) $presentacion->precio);
                $hoja->setCellValue('F'.$fila, (int) $presentacion->stock);
                // G va vacía a propósito: es donde se escribe el pedido.
                $hoja->setCellValue('H'.$fila, "=IF(G{$fila}=\"\",\"\",G{$fila}*E{$fila})");
                $fila++;
            }
        }

        self::darleFormato($hoja, $fila - 1);

        $nombre = 'VEGANLIFE-lista-precios-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(
            fn () => (new Xlsx($libro))->save('php://output'),
            $nombre,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * Los encabezados. Los seis primeros son los que el Importador reconoce
     * solo (ver Importador::autoMapColumns), así que el archivo se puede
     * volver a subir sin mapear nada a mano.
     *
     * "Cant. a pedir" no se llama "Cantidad" a propósito: el Importador toma
     * "cantidad" como sinónimo de stock y al reimportar el archivo pisaría el
     * stock real con lo que se hubiera cargado para el pedido.
     */
    private const COLUMNAS = ['Marca', 'Nombre', 'Categoría', 'Unidad', 'Precio', 'Stock', 'Cant. a pedir', 'Total'];

    /**
     * Escribe la celda como texto y nada más.
     *
     * Excel trata como fórmula toda celda que empiece con =, +, - o @. Un
     * producto llamado "=HYPERLINK(...)" se ejecutaba al abrir la planilla en
     * la máquina de quien la bajara.
     */
    private static function comoTexto(Worksheet $hoja, string $celda, ?string $valor): void
    {
        $hoja->setCellValueExplicit($celda, (string) $valor, DataType::TYPE_STRING);
    }

    /**
     * Encabezado fijo y con filtro, y los pesos con formato de plata. Sin esto
     * el archivo abre como una sábana de 2000 filas donde no se sabe qué
     * columna es cuál en cuanto se baja un poco.
     */
    private static function darleFormato(Worksheet $hoja, int $ultimaFila): void
    {
        $hoja->getStyle('A1:H1')->getFont()->setBold(true);
        $hoja->getStyle('A1:H1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E8F0E3');

        // El filtro de la fila 1 es lo que permite aislar la marca del
        // proveedor de un clic para sacarle la captura.
        $hoja->setAutoFilter('A1:H'.max($ultimaFila, 1));
        $hoja->freezePane('A2');

        foreach (['E', 'H'] as $columna) {
            $hoja->getStyle($columna.'2:'.$columna.max($ultimaFila, 2))
                ->getNumberFormat()->setFormatCode('"$"#,##0.00');
        }

        foreach (['A' => 22, 'B' => 44, 'C' => 20, 'D' => 12, 'E' => 14, 'F' => 8, 'G' => 13, 'H' => 14] as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }
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
            ->whereHas('productos', fn ($q) => $q->where('activo', true))
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
            fn () => print ($html),
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
            ->whereHas('productos', fn ($q) => $q->where('activo', true))
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
