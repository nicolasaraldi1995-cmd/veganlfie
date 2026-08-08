<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
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
        // Las que ya existían y la planilla no traía precio: se dejaron como
        // estaban. Aparte de filas_saltadas porque no es un error, es un renglón
        // que a propósito no se tocó.
        'presentaciones_sin_precio' => 0,
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
            // Un precio ilegible (negativo) no puede tumbar la previsualización
            // entera: se muestra en blanco, que es lo que va a pasar al importar.
            try {
                $fila['precio'] = $this->parsePrice($fila['precio'] ?? null);
            } catch (\InvalidArgumentException) {
                $fila['precio'] = null;
            }

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
     * @var array<string, Marca>
     */
    private array $marcasPorNombre = [];

    /** @var array<string, Categoria> */
    private array $categoriasPorNombre = [];

    /** @var array<string, Producto> */
    private array $productosPorClave = [];

    /** @var array<string, Presentacion> */
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
        // Se indexa por el slug guardado Y por el slug que sale del nombre.
        // No siempre coinciden: si a una marca se le cambia el nombre, el slug
        // queda como estaba ("Bygiro" conserva bygiro-pancakes). Buscando sólo
        // por el nombre no se la encontraba, se intentaba crearla y chocaba
        // contra el índice único del slug, que es lo que mira la base.
        $this->marcasPorNombre = [];
        foreach (Marca::withTrashed()->get() as $marca) {
            $this->marcasPorNombre[$this->claveDeNombre($marca->nombre)] = $marca;
            if (filled($marca->slug)) {
                $this->marcasPorNombre[$marca->slug] ??= $marca;
            }
        }

        $this->categoriasPorNombre = [];
        foreach (Categoria::withTrashed()->get() as $categoria) {
            $this->categoriasPorNombre[$this->claveDeNombre($categoria->nombre)] = $categoria;
            if (filled($categoria->slug)) {
                $this->categoriasPorNombre[$categoria->slug] ??= $categoria;
            }
        }

        // orderBy('activo') deja los activos al final, y keyBy se queda con el
        // último: si un nombre está repetido entre uno dado de baja y uno
        // vigente, se actualiza el vigente. Sin esto el precio nuevo iba a
        // parar al producto que ya no se muestra.
        $this->productosPorClave = Producto::withTrashed()->orderBy('activo')->get()
            ->keyBy(fn (Producto $p) => $this->claveProducto($p->nombre, (int) $p->marca_id))->all();

        $this->presentacionesPorClave = Presentacion::withTrashed()->orderBy('activo')->get()
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

            $precio = $this->parsePrice($row['precio'] ?? null);

            $clave = $producto->id.'|||'.$this->normalizar($unidad);
            $presentacion = $this->presentacionesPorClave[$clave] ?? null;

            // Sin precio usable (celda vacía, "Consultar", o la columna sin
            // mapear): a la que ya existe se le deja el precio que tenía; una
            // nueva no se crea, porque nacería a $0 y comprable. Se cuenta como
            // salteada en vez de pisar un precio bueno con cero.
            if ($precio === null) {
                if ($presentacion) {
                    $this->stats['presentaciones_sin_precio']++;
                } else {
                    $this->stats['filas_saltadas']++;
                }

                continue;
            }

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

    /**
     * ¿Es la lista de precios que exporta la propia web?
     *
     * Se reconoce por su estructura: marcas en <section class="marca"> y cada
     * presentación con su <input class="cant" data-precio>. Así el circuito se
     * cierra: se exporta, se edita, se vuelve a subir.
     */
    private function esListaDeLaWeb(string $path): bool
    {
        $inicio = (string) file_get_contents($path, false, null, 0, 200000);

        return str_contains($inicio, 'class="marca"') && str_contains($inicio, 'data-precio');
    }

    /**
     * Convierte la lista exportada por la web a las mismas columnas que el
     * resto del importador (Nombre, Marca, Categoría, Unidad, Precio).
     *
     * @return Collection<int, array<string, string>>
     */
    private function leerListaDeLaWeb(string $path): Collection
    {
        $doc = new \DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML((string) file_get_contents($path), LIBXML_COMPACT);
        libxml_clear_errors();

        $xp = new \DOMXPath($doc);
        $filas = collect();

        foreach ($xp->query('//section[contains(concat(" ", normalize-space(@class), " "), " marca ")]') ?: [] as $seccion) {
            $marca = $this->textoDe($xp, './/h2', $seccion);

            foreach ($xp->query('.//div[contains(concat(" ", normalize-space(@class), " "), " prod ")]', $seccion) ?: [] as $prod) {
                $nombre = $this->textoDe($xp, './/*[contains(concat(" ", normalize-space(@class), " "), " prod-nom ")]', $prod);
                $categoria = $prod instanceof \DOMElement ? trim($prod->getAttribute('data-cat')) : '';

                foreach ($xp->query('.//input[contains(concat(" ", normalize-space(@class), " "), " cant ")]', $prod) ?: [] as $input) {
                    if (! $input instanceof \DOMElement) {
                        continue;
                    }

                    $unidad = $this->textoDe($xp, './/*[contains(concat(" ", normalize-space(@class), " "), " unidad ")]', $input->parentNode);

                    // El precio sale del atributo, no del texto: el texto está
                    // redondeado para leerlo y perdería los centavos.
                    $filas->push([
                        'Nombre' => $nombre,
                        'Marca' => $marca,
                        'Categoría' => $categoria,
                        'Unidad' => $unidad,
                        'Precio' => $input->getAttribute('data-precio'),
                    ]);
                }
            }
        }

        return $filas;
    }

    /**
     * Texto del primer nodo que coincida, o cadena vacía si no hay ninguno.
     *
     * Se descartan las etiquetas de adorno (NUEVO, SIN TACC, FRÍO...): son
     * hermanas del nombre dentro del mismo bloque, y si se leen quedan pegadas
     * al nombre del producto -- "Pancakes clásicos NUEVO" pasaba por un
     * producto distinto.
     */
    private function textoDe(\DOMXPath $xp, string $consulta, ?\DOMNode $desde): string
    {
        $nodo = $xp->query($consulta, $desde)->item(0);

        if ($nodo === null) {
            return '';
        }

        $copia = $nodo->cloneNode(true);
        $xpCopia = new \DOMXPath($copia->ownerDocument);

        foreach ($xpCopia->query('.//*[contains(@class,"badge") or contains(@class,"tag")]', $copia) ?: [] as $adorno) {
            $adorno->parentNode?->removeChild($adorno);
        }

        return trim(preg_replace('/\s+/u', ' ', $copia->textContent) ?? '');
    }

    public function readFile(string $path, int $headerRow = 1): Collection
    {
        if ($this->esListaDeLaWeb($path)) {
            return $this->leerListaDeLaWeb($path);
        }

        if ($this->esTablaHtml($path)) {
            return $this->leerTablaHtml($path, $headerRow);
        }

        // Leer cada celda como texto crudo, sin que la librería interprete los
        // números. Al leer un CSV o un HTML, "1.105" lo tomaba como uno coma
        // ciento cinco y devolvía el float 1.105 antes de que parsePrice lo
        // viera: el precio ya venía partido por mil desde la lectura, y ningún
        // arreglo en parsePrice lo podía deshacer. Con el texto intacto,
        // parsePrice decide si el punto es de miles o de decimales. En un .xlsx
        // el número está guardado con su tipo, así que esto no lo altera.
        $binderAnterior = Cell::getValueBinder();
        Cell::setValueBinder(new StringValueBinder);

        try {
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
                    $value = $cell->getValue();
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
        } finally {
            Cell::setValueBinder($binderAnterior);
        }

        return $rows;
    }

    public function getHeaders(string $path, int $headerRow = 1): array
    {
        if ($this->esListaDeLaWeb($path)) {
            // Columnas fijas: la lista de la web no tiene encabezados, se arman
            // a partir de su estructura.
            return ['Nombre', 'Marca', 'Categoría', 'Unidad', 'Precio'];
        }

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

    /**
     * Lee un precio de una celda de la planilla.
     *
     * Devuelve null cuando no hay un precio usable (celda vacía, "Consultar",
     * "s/d"): en ese caso el que llama deja el precio como estaba en vez de
     * pisarlo con cero. Antes devolvía 0 y ese cero se guardaba, así que una
     * celda vacía sacaba el producto de la web de a una fila, sin avisar.
     *
     * Lanza si el número es negativo: eso es un dato mal cargado, no una celda
     * en blanco, y conviene que se vea.
     */
    private function parsePrice($value): ?float
    {
        // Sólo los números que ya vienen como número de PHP (una celda numérica
        // de Excel) se toman tal cual. Un texto se interpreta siempre, aunque
        // is_numeric lo acepte: "1.105" es un número válido para PHP (uno coma
        // ciento cinco), y ese atajo era justo el que partía el precio por mil.
        if (is_int($value) || is_float($value)) {
            $price = (float) $value;
        } else {
            $texto = trim((string) $value);

            if ($texto === '') {
                return null;
            }

            // El signo hay que mirarlo antes de limpiar: preg_replace se lo come
            // y "$-100" quedaba convertido en 100, salteando el rechazo de abajo.
            // Cuenta cualquier "-" que aparezca antes del primer dígito, así el
            // símbolo de moneda adelante no lo tapa.
            $negativo = preg_match('/^[^\d]*-/', $texto) === 1;
            $limpio = preg_replace('/[^\d.,]/', '', $texto);

            if ($limpio === '' || preg_match('/\d/', $limpio) !== 1) {
                return null;
            }

            $price = $this->interpretarNumero($limpio) * ($negativo ? -1 : 1);
        }

        if ($price < 0) {
            throw new \InvalidArgumentException("Precio inválido: \"{$value}\".");
        }

        return $price;
    }

    /**
     * Convierte el número ya limpio (sólo dígitos, puntos y comas) a float,
     * decidiendo si el punto separa miles o decimales.
     *
     * El problema estaba acá: "1.105" se tomaba como uno coma ciento cinco y el
     * precio quedaba en la milésima parte. Los precios de almacén casi nunca
     * llevan centavos, así que "1.105", "12.500" y "1.234.567" son lo más común
     * de la lista y todos entraban partidos por mil.
     *
     * Con coma, la coma manda: es el decimal argentino y el punto es de miles
     * ("1.234,56"). Sin coma, el punto es de miles sólo si todos los grupos que
     * le siguen tienen exactamente tres dígitos ("1.105", "1.234.567"); si el
     * último grupo no los tiene, es un decimal a la inglesa ("1.5", "12.50").
     *
     * El precio a la argentina "1.500" con intención de "uno y medio" se lee
     * como mil quinientos. En un catálogo de almacén ese número no existe.
     */
    private function interpretarNumero(string $limpio): float
    {
        $tieneComa = str_contains($limpio, ',');
        $tienePunto = str_contains($limpio, '.');

        if ($tieneComa) {
            // La coma es el decimal; si además hay puntos, son de miles.
            return (float) str_replace(',', '.', str_replace('.', '', $limpio));
        }

        if ($tienePunto) {
            $grupos = explode('.', $limpio);
            $sinElPrimero = array_slice($grupos, 1);
            $todosDeTres = $sinElPrimero !== [] && ! in_array(false, array_map(fn ($g) => strlen($g) === 3, $sinElPrimero), true);

            return $todosDeTres ? (float) implode('', $grupos) : (float) $limpio;
        }

        return (float) $limpio;
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
