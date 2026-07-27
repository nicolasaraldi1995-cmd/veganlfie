<?php

namespace Tests\Feature\Console;

use App\Models\Marca;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecuperarImagenesVeganopolisTest extends TestCase
{
    use RefreshDatabase;

    private function fakeBusqueda(string $id, string $archivo, string $nombre): string
    {
        return <<<HTML
            <a href="producto.php?id={$id}">
              <img class="card-img-top" src="thumb/{$archivo} ">
            </a>
            <div class="card-body">
            <a href="producto.php?id={$id}" style="text-decoration: none;">
              <h6 class="card-title d-none d-sm-block" style="margin-bottom:5px;">{$nombre}</h6>
            HTML;
    }

    public function test_sube_la_imagen_cuando_encuentra_una_coincidencia_fuerte(): void
    {
        Storage::fake('s3');
        Http::fake([
            'veganopolis.com.ar/index.php*' => Http::response($this->fakeBusqueda('99', 'abc123.jpeg', 'Not milk original (clásica)')),
            'veganopolis.com.ar/imagenes/*' => Http::response('contenido-fake-de-imagen', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $marca = Marca::factory()->create();
        $producto = Producto::factory()->create(['marca_id' => $marca->id, 'nombre' => 'Not milk original', 'imagen' => null]);

        $this->artisan('productos:recuperar-imagenes-veganopolis')->assertSuccessful();

        $producto->refresh();
        $this->assertNotNull($producto->imagen);
        Storage::disk('s3')->assertExists($producto->imagen);
        $this->assertEquals('contenido-fake-de-imagen', Storage::disk('s3')->get($producto->imagen));
    }

    public function test_no_toca_el_producto_si_no_hay_ninguna_coincidencia(): void
    {
        Storage::fake('s3');
        Http::fake([
            'veganopolis.com.ar/index.php*' => Http::response(''),
        ]);

        $marca = Marca::factory()->create();
        $producto = Producto::factory()->create(['marca_id' => $marca->id, 'nombre' => 'Producto que no existe en la web vieja', 'imagen' => null]);

        $this->artisan('productos:recuperar-imagenes-veganopolis')->assertSuccessful();

        $this->assertNull($producto->refresh()->imagen);
    }

    public function test_no_aplica_una_coincidencia_debil_por_debajo_del_umbral(): void
    {
        Storage::fake('s3');
        Http::fake([
            'veganopolis.com.ar/index.php*' => Http::response($this->fakeBusqueda('5', 'otro.jpeg', 'Un producto totalmente distinto')),
        ]);

        $marca = Marca::factory()->create();
        $producto = Producto::factory()->create(['marca_id' => $marca->id, 'nombre' => 'Milanesas de garbanzo y boniato', 'imagen' => null]);

        $this->artisan('productos:recuperar-imagenes-veganopolis')->assertSuccessful();

        $this->assertNull($producto->refresh()->imagen);
    }

    public function test_dry_run_no_escribe_nada(): void
    {
        Storage::fake('s3');
        Http::fake([
            'veganopolis.com.ar/index.php*' => Http::response($this->fakeBusqueda('99', 'abc123.jpeg', 'Not milk original (clásica)')),
        ]);

        $marca = Marca::factory()->create();
        $producto = Producto::factory()->create(['marca_id' => $marca->id, 'nombre' => 'Not milk original', 'imagen' => null]);

        $this->artisan('productos:recuperar-imagenes-veganopolis', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull($producto->refresh()->imagen);
        Storage::disk('s3')->assertDirectoryEmpty('productos');
    }
}
