<?php

namespace Tests\Feature;

use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListaPreciosHtmlTest extends TestCase
{
    use RefreshDatabase;

    private function catalogo(): void
    {
        $marca = Marca::factory()->create(['nombre' => 'Chía Graal', 'activo' => true]);
        $producto = Producto::factory()->create([
            'marca_id' => $marca->id,
            'nombre' => 'Aceite de coco neutro',
            'sin_tacc' => true,
            'activo' => true,
        ]);
        Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'unidad' => '180ml',
            'precio' => 4865,
            'activo' => true,
        ]);
    }

    public function test_un_invitado_no_puede_descargar_la_lista(): void
    {
        $this->get('/lista-de-precios/html')->assertRedirect('/login');
    }

    public function test_descarga_un_html_autocontenido_con_los_productos(): void
    {
        $this->catalogo();

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/lista-de-precios/html');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=utf-8');
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));

        $html = $response->streamedContent();

        // Contenido esperado.
        $this->assertStringContainsString('Chía Graal', $html);
        $this->assertStringContainsString('Aceite de coco neutro', $html);
        $this->assertStringContainsString('180ml', $html);
        $this->assertStringContainsString('4.865', $html);
        $this->assertStringContainsString('sin tacc', $html);

        // Tiene que funcionar sin internet una vez descargado: nada de pedidos
        // a servidores externos (fuentes, scripts, imágenes o estilos).
        $this->assertStringNotContainsString('<script src=', $html);
        $this->assertStringNotContainsString('<link rel="stylesheet"', $html);
        $this->assertStringNotContainsString('http://fonts.', $html);
        $this->assertStringNotContainsString('https://fonts.', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
    }

    public function test_no_incluye_marcas_ni_productos_inactivos(): void
    {
        $this->catalogo();

        $marcaInactiva = Marca::factory()->create(['nombre' => 'Marca Dada De Baja', 'activo' => false]);
        $productoInactivo = Producto::factory()->create([
            'marca_id' => $marcaInactiva->id,
            'nombre' => 'Producto Discontinuado',
            'activo' => false,
        ]);
        Presentacion::factory()->create(['producto_id' => $productoInactivo->id, 'activo' => true]);

        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/lista-de-precios/html')
            ->streamedContent();

        $this->assertStringNotContainsString('Marca Dada De Baja', $html);
        $this->assertStringNotContainsString('Producto Discontinuado', $html);
    }
}
