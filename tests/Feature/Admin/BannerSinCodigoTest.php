<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\BannerResource\Pages\CreateBanner;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El destino de un banner es texto libre y termina en el href de la portada.
 * Con "javascript:..." adentro, cualquiera que tocara la imagen ejecutaba ese
 * código en su propio navegador, con su sesión abierta.
 *
 * Hace falta una cuenta del equipo para cargarlo, pero el operador no tiene por
 * qué poder poner código en la página pública.
 */
class BannerSinCodigoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sin pasar por el observador: acá no se prueba el retoque de la imagen,
     * se prueba a dónde apunta el banner.
     */
    private function banner(string $tipo, ?string $valor): Banner
    {
        $banner = new Banner([
            'imagen' => 'banners/prueba.png',
            'destino_tipo' => $tipo,
            'destino_valor' => $valor,
            'orden' => 0,
            'activo' => true,
        ]);
        $banner->saveQuietly();

        return $banner;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function destinosPeligrosos(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript en mayúsculas' => ['JavaScript:alert(1)'],
            'javascript con espacio adelante' => ['   javascript:alert(1)'],
            // Los navegadores ignoran tabs y saltos dentro de una dirección, así
            // que esto se ejecuta igual que "javascript:".
            'javascript partido con un tab' => ["java\tscript:alert(1)"],
            'javascript partido con un salto' => ["java\nscript:alert(1)"],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'vbscript' => ['vbscript:msgbox(1)'],
            'archivo del disco' => ['file:///C:/Windows/System32'],
            // "//" el navegador lo lee como "el mismo esquema, otro dominio".
            'dominio ajeno sin esquema' => ['//sitio-ajeno.com/pagina'],
        ];
    }

    #[DataProvider('destinosPeligrosos')]
    public function test_un_destino_peligroso_no_llega_al_navegador(string $destino): void
    {
        $banner = $this->banner('url', $destino);

        $this->assertNull($banner->url, "Se dibujó como enlace: {$destino}");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function destinosBuenos(): array
    {
        return [
            'https' => ['https://www.instagram.com/veganlife.distribuidoravegana'],
            'http' => ['http://veganlife.com.ar'],
            'HTTPS en mayúsculas' => ['HTTPS://veganlife.com.ar'],
            'una página del propio sitio' => ['/ofertas'],
        ];
    }

    #[DataProvider('destinosBuenos')]
    public function test_un_destino_normal_sigue_funcionando(string $destino): void
    {
        $banner = $this->banner('url', $destino);

        $this->assertSame($destino, $banner->url);
    }

    /** Un banner sin destino válido se sigue viendo: es una imagen sin enlace. */
    public function test_el_banner_se_sigue_mostrando_aunque_el_destino_se_descarte(): void
    {
        $this->banner('url', 'javascript:alert(1)');

        $respuesta = $this->get('/');

        $respuesta->assertOk();
        $respuesta->assertDontSee('javascript:alert(1)', false);
    }

    /** Los destinos internos no pasan por acá y siguen igual. */
    public function test_los_destinos_internos_no_cambian(): void
    {
        $banner = $this->banner('categoria', '3');

        $this->assertStringContainsString('vista=categorias', (string) $banner->url);
    }

    /** Y el que lo carga se entera en el momento, no en silencio. */
    public function test_el_formulario_rechaza_el_destino_peligroso(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(CreateBanner::class)
            ->fillForm([
                'imagen' => null,
                'destino_tipo' => 'url',
                'destino_valor' => 'javascript:alert(1)',
                'orden' => 0,
                'activo' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['destino_valor']);

        $this->assertSame(0, Banner::count());
    }
}
