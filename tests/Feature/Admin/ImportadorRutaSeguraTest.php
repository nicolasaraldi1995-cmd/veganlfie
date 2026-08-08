<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Importador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * La propiedad $archivo del importador es pública en Livewire: viaja al
 * navegador y vuelve, así que con $wire.set se le puede poner una ruta a mano.
 * Sin filtro, "../../../.env" salía de la carpeta de imports y el importador
 * leía cualquier archivo del servidor.
 */
class ImportadorRutaSeguraTest extends TestCase
{
    use RefreshDatabase;

    private function rutaGuardada(string|array|null $archivo): ?string
    {
        $page = new Importador;
        $page->archivo = $archivo;

        $metodo = new \ReflectionMethod($page, 'rutaGuardada');

        return $metodo->invoke($page);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function rutasPeligrosas(): array
    {
        return [
            'sube un nivel' => ['../.env'],
            'sube varios' => ['../../../../.env'],
            'con imports adelante pero se escapa' => ['imports/../../../.env'],
            'ruta absoluta' => ['/etc/passwd'],
            'otra carpeta del disco' => ['storage/logs/laravel.log'],
            'un php envuelto' => ['php://filter/resource=.env'],
        ];
    }

    #[DataProvider('rutasPeligrosas')]
    public function test_una_ruta_que_se_escapa_de_imports_se_rechaza(string $ruta): void
    {
        $this->assertNull($this->rutaGuardada($ruta), "No rechazó: {$ruta}");
        $this->assertNull($this->rutaGuardada(['adjunto-1' => $ruta]));
    }

    public function test_una_ruta_legitima_de_imports_pasa(): void
    {
        $this->assertSame('imports/01ABC.xls', $this->rutaGuardada('imports/01ABC.xls'));
        $this->assertSame('imports/01ABC.xls', $this->rutaGuardada(['adjunto-1' => 'imports/01ABC.xls']));
    }

    public function test_vacio_o_nulo_da_null(): void
    {
        $this->assertNull($this->rutaGuardada(null));
        $this->assertNull($this->rutaGuardada(''));
        $this->assertNull($this->rutaGuardada([]));
    }
}
