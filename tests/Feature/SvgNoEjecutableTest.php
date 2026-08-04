<?php

namespace Tests\Feature;

use App\Filament\Resources\BannerResource;
use App\Filament\Resources\BannerResource\Pages\CreateBanner;
use App\Filament\Resources\CategoriaResource;
use App\Filament\Resources\CategoriaResource\Pages\CreateCategoria;
use App\Filament\Resources\ComboResource;
use App\Filament\Resources\ComboResource\Pages\CreateCombo;
use App\Filament\Resources\MarcaResource;
use App\Filament\Resources\MarcaResource\Pages\CreateMarca;
use App\Filament\Resources\ProductoResource;
use App\Filament\Resources\ProductoResource\Pages\CreateProducto;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Un SVG es texto, y adentro puede llevar <script>. Si se sube como foto de
 * producto y después se sirve desde el propio dominio como image/svg+xml, ese
 * script corre con la sesión de quien abra la imagen: un operador que sube la
 * foto se lleva la sesión del dueño.
 *
 * Filament valida ->image() con "mimetypes:image/*", y image/svg+xml entra en
 * ese comodín. La regla "image" de Laravel sí lo rechaza, pero Filament no la
 * usa. MarcaResource ya listaba los tipos a mano; los demás no.
 */
class SvgNoEjecutableTest extends TestCase
{
    use RefreshDatabase;

    private const SVG_CON_SCRIPT = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>';

    /**
     * @return array<string, array{0: class-string, 1: class-string}>
     */
    public static function formulariosConImagen(): array
    {
        return [
            'Productos' => [ProductoResource::class, CreateProducto::class],
            'Marcas' => [MarcaResource::class, CreateMarca::class],
            'Categorías' => [CategoriaResource::class, CreateCategoria::class],
            'Combos' => [ComboResource::class, CreateCombo::class],
            'Banners' => [BannerResource::class, CreateBanner::class],
        ];
    }

    #[DataProvider('formulariosConImagen')]
    public function test_ningun_formulario_del_panel_acepta_svg(string $resource, string $pagina): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $subidas = $this->subidasDe($resource, $pagina);

        $this->assertNotEmpty($subidas, "No se encontró ningún campo de imagen en {$resource}.");

        foreach ($subidas as $campo) {
            $tipos = $campo->getAcceptedFileTypes() ?? [];

            $this->assertNotContains('image/svg+xml', $tipos, "El campo {$campo->getName()} acepta SVG.");
            $this->assertNotContains(
                'image/*',
                $tipos,
                "El campo {$campo->getName()} acepta image/* a secas, y ahí adentro entra el SVG. "
                .'Hay que listar los tipos como hace MarcaResource.'
            );
            $this->assertNotEmpty($tipos, "El campo {$campo->getName()} no restringe ningún tipo.");
        }
    }

    /**
     * @return list<FileUpload>
     */
    private function subidasDe(string $resource, string $pagina): array
    {
        $componentes = $resource::form(new Form(
            Livewire::test($pagina)->instance()
        ))->getComponents();
        $subidas = [];

        $buscar = function (array $lista) use (&$buscar, &$subidas): void {
            foreach ($lista as $componente) {
                if ($componente instanceof FileUpload) {
                    $subidas[] = $componente;
                }

                if (method_exists($componente, 'getChildComponents')) {
                    $buscar($componente->getChildComponents());
                }
            }
        };

        $buscar($componentes);

        return $subidas;
    }

    /**
     * Segunda barrera, por si alguno quedó subido de antes: aunque el archivo
     * exista, no se entrega de forma que el navegador lo ejecute.
     */
    public function test_un_svg_ya_subido_no_se_sirve_como_svg(): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');
        Storage::disk('public')->put('productos/malicioso.svg', self::SVG_CON_SCRIPT);

        $respuesta = $this->get('/media/productos/malicioso.svg')->assertOk();

        // Los 63 iconos de categoría son SVG y tienen que seguir dibujándose,
        // así que no se deja de servirlos: se les corta la capacidad de hacer
        // algo. Sin scripts, sin pedidos a la red, sin formularios.
        $csp = (string) $respuesta->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString('sandbox', $csp);
    }

    public function test_las_imagenes_de_verdad_se_siguen_sirviendo_bien(): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');
        Storage::disk('public')->put(
            'productos/foto.gif',
            (string) base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7')
        );

        $this->get('/media/productos/foto.gif')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');
    }
}
