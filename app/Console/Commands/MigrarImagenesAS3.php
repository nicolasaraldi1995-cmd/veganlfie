<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('imagenes:migrar-a-s3 {--origen=public} {--destino=s3}')]
#[Description('Copia los archivos del disco local al bucket S3, antes de activar FILAMENT_FILESYSTEM_DISK=s3')]
class MigrarImagenesAS3 extends Command
{
    public function handle(): int
    {
        $origen = $this->option('origen');
        $destino = $this->option('destino');

        $archivos = Storage::disk($origen)->allFiles();
        $total = count($archivos);

        if ($total === 0) {
            $this->info("No hay archivos en el disco '{$origen}' para migrar.");

            return self::SUCCESS;
        }

        $this->info("Migrando {$total} archivos de '{$origen}' a '{$destino}'...");
        $bar = $this->output->createProgressBar($total);

        $fallidos = [];

        foreach ($archivos as $path) {
            $stream = Storage::disk($origen)->readStream($path);

            try {
                if ($stream === null || ! Storage::disk($destino)->writeStream($path, $stream)) {
                    $fallidos[] = $path;
                }
            } catch (\Throwable $e) {
                $fallidos[] = "{$path}: {$e->getMessage()}";
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($fallidos) {
            $this->error(count($fallidos).' archivo(s) fallaron:');
            foreach ($fallidos as $f) {
                $this->line("  - {$f}");
            }

            return self::FAILURE;
        }

        $this->info('Listo, todos los archivos se copiaron correctamente.');

        return self::SUCCESS;
    }
}
