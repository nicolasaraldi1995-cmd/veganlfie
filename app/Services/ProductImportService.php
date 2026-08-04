<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductImportService
{
    private array $stats = [
        'marcas_creadas' => 0,
        'categorias_creadas' => 0,
        'productos_creados' => 0,
        'productos_actualizados' => 0,
        'presentaciones_creadas' => 0,
        'presentaciones_actualizadas' => 0,
        'filas_saltadas' => 0,
        'errores' => [],
    ];

    public function preview(string $path, array $columnMap, int $headerRow = 1): array
    {
        $rows = $this->readFile($path, $headerRow);

        // El precio se muestra ya interpretado. Si se mostrara el texto crudo,
        // "1.105,00" (mil ciento cinco pesos) aparecía como $1,11 y parecía que
        // la importación iba a romper los precios, cuando en realidad los lee
        // bien.
        $mapped = $this->mapColumns($rows, $columnMap)->take(20)->map(function (array $fila) {
            $fila['precio'] = $this->parsePrice($fila['precio'] ?? 0);

            return $fila;
        });

        return [
            'total_filas' => $rows->count(),
            'preview' => $mapped->values()->toArray(),
            'marcas_nuevas' => $this->detectNewBrands($rows, $columnMap),
            'categorias_nuevas' => $this->detectNewCategories($rows, $columnMap),
        ];
    }

    /**
     * Catálogo cargado en memoria antes de empezar. Sin esto el importador
     * hacía una consulta por marca, por categoría, por producto y por
     * presentación de cada fila: 7244 consultas para 1879 filas. Con la base en
     * otro servidor eso tardaba minutos y la importación moría por timeout.
     *
     * @var array<string, \App\Models\Marca>
     */
    private array $marcasPorNombre = [];

    /** @var array<string, \App\Models\Categoria> */
    private array $categoriasPorNombre = [];

    /** @var array<string, \App\Models\Producto> */
    private array $productosPorClave = [];

    /** @var array<string, \App\Models\Presentacion> */
    private array $presentacionesPorClave = [];

    public function import(string $path, array $columnMap, int $headerRow = 1, array $options = []): array
    {
        $rows = $this->readFile($path, $headerRow);
        $mapped = $this->mapColumns($rows, $columnMap);

        $this->precargarCatalogo();

        DB::beginTransaction();
        try {
            $grouped = $mapped->groupBy(fn ($row) => mb_strtolower(trim($row['nombre'] ?? '')).'|||'.mb_strtolower(trim($row['marca'] ?? '')));

            foreach ($grouped as $key => $presentaciones) {
                $first = $presentaciones->first();

                if (empty($first['nombre']) || empty($first['marca'])) {
                    $this->stats['filas_saltadas'] += $presentaciones->count();

                    continue;
                }

                try {
                    // Transacción anidada (savepoint): si falla algo a mitad del grupo
                    // (ej. un precio inválido en una de sus presentaciones), se revierte
                    // solo lo de este producto en vez de dejarlo huérfano sin presentaciones.
                    DB::transaction(fn () => $this->importProductGroup($first, $presentaciones, $options, $columnMap));
                } catch (\Throwable $e) {
                    $this->stats['errores'][] = "Error en '{$first['nombre']}': {$e->getMessage()}";
                    $this->stats['filas_saltadas'] += $presentaciones->count();
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->stats['errores'][] = "Error general: {$e->getMessage()}";
        }

        return $this->stats;
    }

    /**
     * Trae todo el catálogo de una sola vez (4 consultas) para que después el
     * recorrido de las filas no tenga que ir a la base a buscar nada.
     */
    private function precargarCatalogo(): void
    {
        $this->marcasPorNombre = Marca::withTrashed()->get()
            ->keyBy(fn (Marca $m) => $this->claveDeNombre($m->nombre))->all();

        $this->categoriasPorNombre = Categoria::withTrashed()->get()
            ->keyBy(fn (Categoria $c) => $this->claveDeNombre($c->nombre))->all();

        $this->productosPorClave = Producto::withTrashed()->get()
            ->keyBy(fn (Producto $p) => $this->claveProducto($p->nombre, (int) $p->marca_id))->all();

        $this->presentacionesPorClave = Presentacion::withTrashed()->get()
            ->keyBy(fn (Presentacion $p) => $p->producto_id.'|||'.$this->normalizar((string) $p->unidad))->all();
    }

    /**
     * Clave con la que se buscan marcas y categorías: el mismo slug que la base
     * tiene como índice único.
     *
     * Antes la comparación la hacía MySQL, que ignora mayúsculas Y acentos: para
     * la base "Crudda- Barras proteícas" y "Crudda - Barras proteicas" son la
     * misma marca. Al pasar la búsqueda a PHP eso se perdió, no encontraba la
     * marca existente, intentaba crearla y chocaba contra el índice único del
     * slug. Usar el slug como clave hace que las dos escrituras caigan juntas,
     * que es exactamente el criterio de la base.
     */
    private function claveDeNombre(?string $nombre): string
    {
        $nombre = trim((string) $nombre);

        return Str::slug($nombre) ?: $this->normalizar($nombre);
    }

    /**
     * Minúsculas y sin acentos, como compara MySQL.
     */
    private function normalizar(string $texto): string
    {
        return mb_strtolower(Str::ascii(trim($texto)));
    }

    private function claveProducto(string $nombre, int $marcaId): string
    {
        return $this->normalizar($nombre).'|||'.$marcaId;
    }

    private function importProductGroup(array $first, Collection $presentaciones, array $options, array $columnMap): void
    {
        // Las marcas y categorías borradas siguen ocupando su nombre/slug a nivel
        // de base: si el Excel las vuelve a mencionar hay que restaurarlas, no
        // crear otra con el mismo nombre (el índice único de "slug" lo rechaza).
        $marcaNombre = trim($first['marca']);
        $marca = $this->marcasPorNombre[$this->claveDeNombre($marcaNombre)] ?? null;
        if ($marca) {
            if ($marca->trashed()) {
                $marca->restore();
            }
        } else {
            $marca = Marca::create(['nombre' => $marcaNombre]);
            $this->marcasPorNombre[$this->claveDeNombre($marcaNombre)] = $marca;
            $this->stats['marcas_creadas']++;
        }

        $categoriaNombre = trim($first['categoria'] ?? 'Sin categoría');
        $categoria = $this->categoriasPorNombre[$this->claveDeNombre($categoriaNombre)] ?? null;
        if ($categoria) {
            if ($categoria->trashed()) {
                $categoria->restore();
            }
        } else {
            $categoria = Categoria::create(['nombre' => $categoriaNombre]);
            $this->categoriasPorNombre[$this->claveDeNombre($categoriaNombre)] = $categoria;
            $this->stats['categorias_creadas']++;
        }

        $producto = $this->productosPorClave[$this->claveProducto($first['nombre'], (int) $marca->id)] ?? null;

        $sinTacc = $this->parseBool($first['sin_tacc'] ?? null);
        $congelado = $this->parseBool($first['congelado'] ?? null);
        $nuevo = $this->parseBool($first['nuevo'] ?? null);

        if ($producto) {
            if ($options['actualizar_existentes'] ?? true) {
                $datosActualizar = ['categoria_id' => $categoria->id];

                // Estos flags solo se pisan si el Excel realmente trae esa columna
                // mapeada: si no la trae, no hay forma de saber el valor real y hay
                // que dejar lo que el producto ya tenía en vez de resetearlo a "no".
                if (! empty($columnMap['sin_tacc'])) {
                    $datosActualizar['sin_tacc'] = $sinTacc;
                }
                if (! empty($columnMap['congelado'])) {
                    $datosActualizar['congelado'] = $congelado;
                }
                if (! empty($columnMap['nuevo'])) {
                    $datosActualizar['nuevo'] = $nuevo;
                }

                // save() en vez de update(): si nada cambió no manda ninguna
                // consulta. La mayoría de las filas del Excel vienen iguales a
                // lo que ya está cargado.
                $producto->fill($datosActualizar);
                if ($producto->isDirty()) {
                    $producto->save();
                }
                $this->stats['productos_actualizados']++;
            }
        } else {
            $producto = Producto::create([
                'nombre' => trim($first['nombre']),
                'marca_id' => $marca->id,
                'categoria_id' => $categoria->id,
                'sin_tacc' => $sinTacc,
                'congelado' => $congelado,
                'nuevo' => $nuevo,
            ]);
            $this->productosPorClave[$this->claveProducto($producto->nombre, (int) $marca->id)] = $producto;
            $this->stats['productos_creados']++;
        }

        foreach ($presentaciones as $row) {
            $unidad = trim($row['unidad'] ?? '1u');
            if (empty($unidad)) {
                $unidad = '1u';
            }

            $precio = $this->parsePrice($row['precio'] ?? 0);

            $clave = $producto->id.'|||'.$this->normalizar($unidad);
            $presentacion = $this->presentacionesPorClave[$clave] ?? null;

            if ($presentacion) {
                $presentacion->precio = $precio;
                if ($presentacion->isDirty()) {
                    $presentacion->save();
                }
                $this->stats['presentaciones_actualizadas']++;
            } else {
                $this->presentacionesPorClave[$clave] = Presentacion::create([
                    'producto_id' => $producto->id,
                    'unidad' => $unidad,
                    'precio' => $precio,
                    'stock' => max(0, (int) ($row['stock'] ?? 0)),
                ]);
                $this->stats['presentaciones_creadas']++;
            }
        }
    }

    /**
     * ¿El archivo es en realidad una tabla HTML?
     *
     * La lista que exporta el sistema viejo se llama .xls pero por dentro es
     * HTML. PhpSpreadsheet la lee, pero tarda 26 segundos para 1900 filas, y el
     * archivo se lee tres veces (encabezados, previsualización e importación).
     * Leerla a mano es cuestión de milisegundos.
     */
    private function esTablaHtml(string $path): bool
    {
        $inicio = (string) file_get_contents($path, false, null, 0, 1024);

        return (bool) preg_match('/<\s*(table|html|meta)\b/i', $inicio);
    }

    /**
     * Todas las filas del archivo como listas de celdas, en crudo.
     *
     * @return array<int, array<int, string>>
     */
    private function celdasDeTablaHtml(string $path): array
    {
        $doc = new \DOMDocument;
        // El HTML exportado no es válido del todo; los avisos no importan.
        @$doc->loadHTML('<?xml encoding="UTF-8">'.file_get_contents($path));

        $filas = [];
        foreach ($doc->getElementsByTagName('tr') as $tr) {
            $celdas = [];
            foreach ($tr->childNodes as $celda) {
                if ($celda instanceof \DOMElement && in_array(strtolower($celda->nodeName), ['td', 'th'], true)) {
                    $celdas[] = $this->limpiarCelda($celda->textContent);
                }
            }
            $filas[] = $celdas;
        }

        return $filas;
    }

    /**
     * Deja la celda como la vería una planilla.
     *
     * - Los espacios repetidos se juntan en uno: en HTML se ven como uno solo,
     *   pero si se dejan tal cual, "Repelente  bactericida" y "Repelente
     *   bactericida" pasan por productos distintos y se duplica el producto.
     * - Una fórmula sin calcular (la fila de totales al final del archivo) vale
     *   como celda vacía; si no, se creaba una marca llamada "=SUMA(...)".
     */
    private function limpiarCelda(string $texto): string
    {
        $limpio = trim(preg_replace('/\s+/u', ' ', html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        return str_starts_with($limpio, '=') ? '' : $limpio;
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function leerTablaHtml(string $path, int $headerRow): Collection
    {
        $filas = $this->celdasDeTablaHtml($path);
        $encabezados = $filas[$headerRow - 1] ?? [];
        $datos = collect();

        foreach (array_slice($filas, $headerRow) as $celdas) {
            $fila = [];
            $tieneDatos = false;

            foreach ($celdas as $i => $valor) {
                $fila[$encabezados[$i] ?? "col_{$i}"] = $valor;
                if ($valor !== '') {
                    $tieneDatos = true;
                }
            }

            if ($tieneDatos) {
                $datos->push($fila);
            }
        }

        return $datos;
    }

    public function readFile(string $path, int $headerRow = 1): Collection
    {
        if ($this->esTablaHtml($path)) {
            return $this->leerTablaHtml($path, $headerRow);
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = collect();

        $headers = [];
        foreach ($sheet->getRowIterator($headerRow, $headerRow) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $headers[] = trim((string) $cell->getValue());
            }
        }

        foreach ($sheet->getRowIterator($headerRow + 1) as $row) {
            $rowData = [];
            $colIndex = 0;
            $hasData = false;

            foreach ($row->getCellIterator() as $cell) {
                $value = $cell->getCalculatedValue();
                $key = $headers[$colIndex] ?? "col_{$colIndex}";
                $rowData[$key] = $value;
                if ($value !== null && $value !== '') {
                    $hasData = true;
                }
                $colIndex++;
            }

            if ($hasData) {
                $rows->push($rowData);
            }
        }

        return $rows;
    }

    public function getHeaders(string $path, int $headerRow = 1): array
    {
        if ($this->esTablaHtml($path)) {
            // De la fila de encabezados en sí, no de la primera fila de datos:
            // esa puede venir incompleta (el archivo trae filas separadoras con
            // una sola celda) y se perdían casi todas las columnas.
            return array_values(array_filter(
                $this->celdasDeTablaHtml($path)[$headerRow - 1] ?? [],
                fn ($h) => trim((string) $h) !== ''
            ));
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $headers = [];

        foreach ($sheet->getRowIterator($headerRow, $headerRow) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $val = trim((string) $cell->getValue());
                if ($val !== '') {
                    $headers[] = $val;
                }
            }
        }

        return $headers;
    }

    private function mapColumns(Collection $rows, array $columnMap): Collection
    {
        return $rows->map(function ($row) use ($columnMap) {
            $mapped = [];
            foreach ($columnMap as $field => $header) {
                if ($header && isset($row[$header])) {
                    $mapped[$field] = $row[$header];
                } else {
                    $mapped[$field] = null;
                }
            }

            return $mapped;
        })->filter(fn ($row) => ! empty($row['nombre']));
    }

    private function detectNewBrands(Collection $rows, array $columnMap): array
    {
        $header = $columnMap['marca'] ?? null;
        if (! $header) {
            return [];
        }

        $excelBrands = $rows->pluck($header)->filter()->map(fn ($v) => trim($v))->unique();
        $existing = Marca::pluck('nombre')->map(fn ($v) => mb_strtolower($v));

        return $excelBrands->filter(fn ($b) => ! $existing->contains(mb_strtolower($b)))->values()->toArray();
    }

    private function detectNewCategories(Collection $rows, array $columnMap): array
    {
        $header = $columnMap['categoria'] ?? null;
        if (! $header) {
            return [];
        }

        $excelCats = $rows->pluck($header)->filter()->map(fn ($v) => trim($v))->unique();
        $existing = Categoria::pluck('nombre')->map(fn ($v) => mb_strtolower($v));

        return $excelCats->filter(fn ($c) => ! $existing->contains(mb_strtolower($c)))->values()->toArray();
    }

    private function parsePrice($value): float
    {
        if (is_numeric($value)) {
            $price = (float) $value;
        } else {
            $cleaned = preg_replace('/[^\d.,]/', '', (string) $value);

            if (str_contains($cleaned, ',') && str_contains($cleaned, '.')) {
                // Formato argentino ("1.234,56"): "." separa miles, "," separa decimales.
                $cleaned = str_replace('.', '', $cleaned);
            }
            $cleaned = str_replace(',', '.', $cleaned);

            $price = (float) $cleaned;
        }

        if ($price < 0) {
            throw new \InvalidArgumentException("Precio inválido: \"{$value}\".");
        }

        return $price;
    }

    private function parseBool($value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        $lower = mb_strtolower(trim((string) $value));

        return in_array($lower, ['1', 'si', 'sí', 'true', 'yes', 'x']);
    }
}
