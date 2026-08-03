<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListaPreciosTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_pdf_de_lista_de_precios_se_descarga_correctamente(): void
    {
        $producto = Producto::factory()->create(['categoria_id' => Categoria::factory()->create()->id]);
        Presentacion::factory()->create(['producto_id' => $producto->id]);

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/lista-de-precios/pdf');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }
}
