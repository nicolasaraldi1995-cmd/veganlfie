<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\BannerResource;
use App\Filament\Resources\BannerResource\Pages\CreateBanner;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BannerFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_formulario_de_banner_carga(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(CreateBanner::class)
            ->assertSuccessful();
    }

    public function test_el_campo_de_imagen_admite_archivos_grandes_y_trae_editor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $campo = collect(BannerResource::form(new Form(
            Livewire::test(CreateBanner::class)->instance()
        ))->getComponents())
            ->first(fn ($c) => $c instanceof FileUpload && $c->getName() === 'imagen');

        $this->assertNotNull($campo);
        // 2 MB frenaba una imagen de banner de 1600x400 en buena calidad.
        $this->assertGreaterThanOrEqual(8192, $campo->getMaxSize());
        $this->assertTrue($campo->hasImageEditor(), 'Sin editor no se puede recortar ni acomodar la imagen al subirla.');
    }
}
