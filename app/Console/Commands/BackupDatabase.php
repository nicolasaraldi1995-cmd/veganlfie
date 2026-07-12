<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep=14 : Cuántos backups diarios conservar}';

    protected $description = 'Genera un dump comprimido de la base de datos y borra los backups más viejos que el límite';

    public function handle(): int
    {
        $connectionName = config('database.default');
        $config = config("database.connections.{$connectionName}");

        if (($config['driver'] ?? null) !== 'mysql') {
            $this->error("Este comando solo soporta MySQL (conexión activa: {$connectionName}).");

            return self::FAILURE;
        }

        $mysqldump = $this->resolveMysqldumpBinary();

        if (! $mysqldump) {
            $this->error('No se encontró el ejecutable mysqldump. Si no está en el PATH, configurá BACKUP_MYSQLDUMP_PATH en el .env con la ruta completa.');

            return self::FAILURE;
        }

        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $filename = 'veganlife-'.now()->format('Y-m-d_H-i-s').'.sql.gz';
        $path = $backupDir.DIRECTORY_SEPARATOR.$filename;

        // Credenciales por archivo temporal (no en argumentos ni env) para que
        // no queden expuestas en la lista de procesos del sistema.
        $credentialsFile = tempnam(sys_get_temp_dir(), 'veganlife_db_');
        file_put_contents($credentialsFile, sprintf(
            "[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n",
            $config['username'],
            $config['password'],
            $config['host'],
            $config['port']
        ));
        @chmod($credentialsFile, 0600);

        try {
            $process = new Process([
                $mysqldump,
                "--defaults-extra-file={$credentialsFile}",
                '--single-transaction',
                '--routines',
                '--triggers',
                $config['database'],
            ]);
            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful()) {
                $this->error('mysqldump falló: '.$process->getErrorOutput());
                Log::channel('single')->error('Backup de base de datos falló', ['error' => $process->getErrorOutput()]);

                return self::FAILURE;
            }

            $gzip = gzopen($path, 'w9');
            gzwrite($gzip, $process->getOutput());
            gzclose($gzip);
        } finally {
            @unlink($credentialsFile);
        }

        $sizeKb = round(filesize($path) / 1024, 1);
        $this->info("Backup creado: {$filename} ({$sizeKb} KB)");
        Log::channel('single')->info('Backup de base de datos generado', ['file' => $filename, 'size_kb' => $sizeKb]);

        $deleted = $this->pruneOldBackups($backupDir, (int) $this->option('keep'));
        if ($deleted > 0) {
            $this->line("Se borraron {$deleted} backup(s) viejo(s).");
        }

        return self::SUCCESS;
    }

    public function pruneOldBackups(string $backupDir, int $keep): int
    {
        $backups = collect(File::files($backupDir))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.sql.gz'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        $toDelete = $backups->slice($keep);

        foreach ($toDelete as $old) {
            File::delete($old->getPathname());
        }

        return $toDelete->count();
    }

    private function resolveMysqldumpBinary(): ?string
    {
        if ($configured = env('BACKUP_MYSQLDUMP_PATH')) {
            return file_exists($configured) ? $configured : null;
        }

        $finder = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        $process = new Process([$finder, 'mysqldump']);
        $process->run();

        if ($process->isSuccessful()) {
            return trim(strtok($process->getOutput(), "\n"));
        }

        // Conveniencia para Laragon en Windows, donde mysqldump no suele estar en el PATH.
        if (PHP_OS_FAMILY === 'Windows') {
            $matches = glob('C:/laragon/bin/mysql/*/bin/mysqldump.exe');
            if (! empty($matches)) {
                return $matches[0];
            }
        }

        return null;
    }
}
