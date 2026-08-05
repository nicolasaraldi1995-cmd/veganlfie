<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\CategoriaResource;
use App\Filament\Resources\CategoriaResource\Pages\CreateCategoria;
use App\Filament\Resources\MarcaResource;
use App\Filament\Resources\MarcaResource\Pages\CreateMarca;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecorteRedondoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string, class-string, string}>
     */
    public static function camposDeImagen(): array
    {
        return [
            'categoría' => [CategoriaResource::class, CreateCategoria::class, 'imagen'],
            'marca' => [MarcaResource::class, CreateMarca::class, 'logo'],
        ];
    }

    /**
     * @param  class-string  $resource
     * @param  class-string  $pagina
     */
    #[DataProvider('camposDeImagen')]
    public function test_la_imagen_se_recorta_en_redondo_al_subirla(string $resource, string $pagina, string $campo): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $componente = collect($resource::form(new Form(
            Livewire::test($pagina)->instance()
        ))->getComponents())
            ->first(fn ($c) => $c instanceof FileUpload && $c->getName() === $campo);

        $this->assertNotNull($componente);
        $this->assertTrue($componente->hasImageEditor(), 'Sin editor no se puede elegir el recorte.');
        // El recorte redondo estilo WhatsApp: círculo + cuadrado obligatorio,
        // porque en la web se muestra dentro de un círculo.
        $this->assertTrue($componente->hasCircleCropper(), 'Falta el recorte circular.');
        $this->assertSame('1:1', $componente->getImageCropAspectRatio());
    }
}
