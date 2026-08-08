<?php

namespace App\Console\Commands;

use App\Services\ProductImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

#[Signature('app:importar-productos-desde-url {url} {--header-row=5}')]
#[Description('Importa productos desde un Excel/CSV accesible por URL, sin pasar por el widget de carga de Filament (workaround del bug de Livewire con archivos).')]
class ImportarProductosDesdeUrl extends Command
{
    public function handle(): int
    {
        $url = (string) $this->argument('url');
        $headerRow = (int) $this->option('header-row');

        // Sólo http/https: sin esto, file_get_contents aceptaba
        // "file:///etc/passwd", "php://filter/..." y la dirección de metadatos
        // de la nube (169.254.169.254), que devuelve credenciales temporales.
        if (! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            $this->error('La URL tiene que empezar con http:// o https://.');

            return self::FAILURE;
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'import_').'.xlsx';

        $this->info("Descargando {$url}...");
        $respuesta = Http::timeout(60)->get($url);
        if (! $respuesta->successful()) {
            $this->error('No se pudo descargar el archivo.');

            return self::FAILURE;
        }
        file_put_contents($tmpPath, $respuesta->body());

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
