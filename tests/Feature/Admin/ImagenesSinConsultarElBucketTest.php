<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\BannerResource;
use App\Filament\Resources\CategoriaResource;
use App\Filament\Resources\ComboResource;
use App\Filament\Resources\MarcaResource;
use App\Filament\Resources\ProductoResource;
use App\Models\User;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Filament, por cada fila con imagen, le pregunta al disco si el archivo
 * existe. Con el disco en un bucket eso es una consulta por red por fila: la
 * lista de productos tardaba tanto que el servidor cortaba con un 504. En
 * local no se notaba porque el disco está en la misma máquina.
 */
class ImagenesSinConsultarElBucketTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string, class-string}>
     */
    public static function recursosConImagen(): array
    {
        return [
            'productos' => [ProductoResource::class, ProductoResource\Pages\ListProductos::class],
            'marcas' => [MarcaResource::class, MarcaResource\Pages\ListMarcas::class],
            'categorías' => [CategoriaResource::class, CategoriaResource\Pages\ListCategorias::class],
            'combos' => [ComboResource::class, ComboResource\Pages\ListCombos::class],
            'banners' => [BannerResource::class, BannerResource\Pages\ListBanners::class],
        ];
    }

    /**
     * @param  class-string  $resource
     * @param  class-string  $pagina
     */
    #[DataProvider('recursosConImagen')]
    public function test_la_columna_de_imagen_no_consulta_el_disco_por_cada_fila(string $resource, string $pagina): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $columnas = collect($resource::table(
            Table::make(Livewire::test($pagina)->instance())
        )->getColumns());

        $imagenes = $columnas->filter(fn ($c) => $c instanceof ImageColumn);

        $this->assertNotEmpty($imagenes, 'Se esperaba al menos una columna de imagen.');

        foreach ($imagenes as $columna) {
            $this->assertFalse(
                $columna->shouldCheckFileExistence(),
                "La columna '{$columna->getName()}' consulta el disco por cada fila."
            );
        }
    }
}
