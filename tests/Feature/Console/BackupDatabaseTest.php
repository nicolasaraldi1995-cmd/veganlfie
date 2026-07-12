<?php

namespace Tests\Feature\Console;

use App\Console\Commands\BackupDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_fails_gracefully_on_a_non_mysql_connection(): void
    {
        // El entorno de test usa sqlite (ver phpunit.xml); el comando debe
        // rechazarlo con un mensaje claro en vez de romper.
        $this->artisan('backup:database')
            ->assertFailed();
    }

    public function test_prune_old_backups_keeps_only_the_newest_n(): void
    {
        $dir = storage_path('app/backups-test');
        File::ensureDirectoryExists($dir);

        $files = [];
        foreach (range(1, 5) as $i) {
            $path = "{$dir}/veganlife-{$i}.sql.gz";
            file_put_contents($path, 'x');
            touch($path, now()->subDays(5 - $i)->timestamp);
            $files[] = $path;
        }

        (new BackupDatabase)->pruneOldBackups($dir, 2);

        $remaining = collect(File::files($dir))->map(fn ($f) => $f->getFilename())->sort()->values();

        $this->assertCount(2, $remaining);
        $this->assertTrue($remaining->contains('veganlife-4.sql.gz'));
        $this->assertTrue($remaining->contains('veganlife-5.sql.gz'));

        File::deleteDirectory($dir);
    }
}
