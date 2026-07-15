<?php

namespace App\Console\Commands;

use App\Services\ProductImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:importar-productos-desde-url {url} {--header-row=5}')]
#[Description('Importa productos desde un Excel/CSV accesible por URL, sin pasar por el widget de carga de Filament (workaround del bug de Livewire con archivos).')]
class ImportarProductosDesdeUrl extends Command
{
    public function handle(): int
    {
        $url = $this->argument('url');
        $headerRow = (int) $this->option('header-row');

        $tmpPath = tempnam(sys_get_temp_dir(), 'import_').'.xlsx';

        $this->info("Descargando {$url}...");
        $contents = file_get_contents($url);
        if ($contents === false) {
            $this->error('No se pudo descargar el archivo.');

            return self::FAILURE;
        }
        file_put_contents($tmpPath, $contents);

        $columnMap = [
            'nombre' => 'Nombre',
            'marca' => 'Marca',
            'categoria' => 'Categoría',
            'unidad' => 'Unidad',
            'precio' => 'Precio',
            'stock' => 'Cantidad',
        ];

        $this->info('Importando...');
        $service = new ProductImportService;
        $stats = $service->import($tmpPath, $columnMap, $headerRow);

        unlink($tmpPath);

        $this->newLine();
        foreach ($stats as $key => $value) {
            if ($key === 'errores') {
                continue;
            }
            $this->line("{$key}: {$value}");
        }

        if (! empty($stats['errores'])) {
            $this->newLine();
            $this->warn('Errores:');
            foreach ($stats['errores'] as $error) {
                $this->line("  - {$error}");
            }
        }

        return self::SUCCESS;
    }
}
