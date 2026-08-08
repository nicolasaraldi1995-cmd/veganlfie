<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Importador;
use App\Models\Marca;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MenoresAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    // --- #35: backup no borra todo con --keep=0 ----------------------------

    public function test_backup_rechaza_keep_cero(): void
    {
        $this->artisan('backup:database', ['--keep' => '0'])->assertFailed();
    }

    public function test_backup_rechaza_keep_no_numerico(): void
    {
        $this->artisan('backup:database', ['--keep' => 'abc'])->assertFailed();
    }

    // --- #38: dos marcas con el mismo slug no chocan -----------------------

    public function test_dos_marcas_que_generan_el_mismo_slug_conviven(): void
    {
        $a = Marca::create(['nombre' => 'Café', 'activo' => true]);
        $b = Marca::create(['nombre' => 'Cafe', 'activo' => true]);

        $this->assertNotSame($a->slug, $b->slug, 'Las dos marcas quedaron con el mismo slug.');
        $this->assertSame('cafe', $a->slug);
        $this->assertSame('cafe-2', $b->slug);
    }

    // --- #43: no se importa sin previsualizar ------------------------------

    public function test_importar_sin_previsualizar_no_hace_nada(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(Importador::class)
            ->assertSet('step', 'upload')
            ->call('runImport')
            ->assertSet('step', 'upload');  // no avanzó a 'result'
    }
}
