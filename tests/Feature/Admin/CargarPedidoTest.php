<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\CargarPedido;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CargarPedidoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_buscador_no_devuelve_productos_inactivos_de_una_marca_que_matchea(): void
    {
        $operador = User::factory()->create(['role' => 'operador']);
        $marca = Marca::factory()->create(['nombre' => 'MarcaBuscable']);

        $activo = Producto::factory()->create(['marca_id' => $marca->id, 'nombre' => 'Producto Activo', 'activo' => true]);
        Presentacion::factory()->create(['producto_id' => $activo->id]);

        $inactivo = Producto::factory()->create(['marca_id' => $marca->id, 'nombre' => 'Producto Inactivo', 'activo' => false]);
        Presentacion::factory()->create(['producto_id' => $inactivo->id]);

        $resultados = Livewire::actingAs($operador)
            ->test(CargarPedido::class)
            ->set('busqueda', 'MarcaBuscable')
            ->get('resultados');

        $nombres = collect($resultados)->pluck('nombre');

        $this->assertContains('Producto Activo', $nombres);
        $this->assertNotContains('Producto Inactivo', $nombres);
    }
}
