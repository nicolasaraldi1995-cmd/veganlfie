<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        $mapped = $this->mapColumns($rows, $columnMap)->take(20);

        return [
            'total_filas' => $rows->count(),
            'preview' => $mapped->values()->toArray(),
            'marcas_nuevas' => $this->detectNewBrands($rows, $columnMap),
            'categorias_nuevas' => $this->detectNewCategories($rows, $columnMap),
        ];
    }

    public function import(string $path, array $columnMap, int $headerRow = 1, array $options = []): array
    {
        $rows = $this->readFile($path, $headerRow);
        $mapped = $this->mapColumns($rows, $columnMap);

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
                    $this->importProductGroup($first, $presentaciones, $options);
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

    private function importProductGroup(array $first, Collection $presentaciones, array $options): void
    {
        $marca = Marca::firstOrCreate(
            ['nombre' => trim($first['marca'])],
        );
        if ($marca->wasRecentlyCreated) {
            $this->stats['marcas_creadas']++;
        }

        $categoriaNombre = trim($first['categoria'] ?? 'Sin categoría');
        $categoria = Categoria::firstOrCreate(
            ['nombre' => $categoriaNombre],
        );
        if ($categoria->wasRecentlyCreated) {
            $this->stats['categorias_creadas']++;
        }

        $producto = Producto::where('nombre', trim($first['nombre']))
            ->where('marca_id', $marca->id)
            ->first();

        $sinTacc = $this->parseBool($first['sin_tacc'] ?? null);
        $congelado = $this->parseBool($first['congelado'] ?? null);
        $nuevo = $this->parseBool($first['nuevo'] ?? null);

        if ($producto) {
            if ($options['actualizar_existentes'] ?? true) {
                $producto->update([
                    'categoria_id' => $categoria->id,
                    'sin_tacc' => $sinTacc,
                    'congelado' => $congelado,
                    'nuevo' => $nuevo,
                ]);
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
            $this->stats['productos_creados']++;
        }

        foreach ($presentaciones as $row) {
            $unidad = trim($row['unidad'] ?? '1u');
            if (empty($unidad)) {
                $unidad = '1u';
            }

            $precio = $this->parsePrice($row['precio'] ?? 0);

            $presentacion = Presentacion::where('producto_id', $producto->id)
                ->where('unidad', $unidad)
                ->first();

            if ($presentacion) {
                $presentacion->update(['precio' => $precio]);
                $this->stats['presentaciones_actualizadas']++;
            } else {
                Presentacion::create([
                    'producto_id' => $producto->id,
                    'unidad' => $unidad,
                    'precio' => $precio,
                    'stock' => (int) ($row['stock'] ?? 0),
                ]);
                $this->stats['presentaciones_creadas']++;
            }
        }
    }

    public function readFile(string $path, int $headerRow = 1): Collection
    {
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
            return (float) $value;
        }
        $cleaned = preg_replace('/[^\d.,]/', '', (string) $value);
        $cleaned = str_replace(',', '.', $cleaned);

        return (float) $cleaned ?: 0;
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
