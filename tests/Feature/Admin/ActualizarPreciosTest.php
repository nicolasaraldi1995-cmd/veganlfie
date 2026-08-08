<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\ActualizarPrecios;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActualizarPreciosTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_porcentaje_que_dejaria_el_precio_en_cero_o_negativo_es_rechazado(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $marca = Marca::factory()->create();
        $producto = Producto::factory()->create(['marca_id' => $marca->id]);
        $presentacion = Presentacion::factory()->create(['producto_id' => $producto->id, 'precio' => 1000]);

        Livewire::actingAs($admin)
            ->test(ActualizarPrecios::class)
            ->set('marca_id', $marca->id)
            ->set('porcentaje', -150)
            ->call('aplicarAumento')
            ->assertHasErrors(['porcentaje']);

        $this->assertEquals(1000, $presentacion->fresh()->precio);
    }

    public function test_un_porcentaje_valido_actualiza_el_precio_de_toda_la_marca(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $marca = Marca::factory()->create();
        $producto = Producto::factory()->create(['marca_id' => $marca->id]);
        $presentacion = Presentacion::factory()->create(['producto_id' => $producto->id, 'precio' => 1000]);

        Livewire::actingAs($admin)
            ->test(ActualizarPrecios::class)
            ->set('marca_id', $marca->id)
            ->set('porcentaje', 10)
            ->call('aplicarAumento')
            ->assertHasNoErrors();

        $this->assertEquals(1100, $presentacion->fresh()->precio);
    }

    /** Toda la marca se mueve junta, aunque tenga varias presentaciones. */
    public function test_mueve_todas_las_presentaciones_de_la_marca(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $marca = Marca::factory()->create();

        $precios = collect(range(1, 5))->map(function ($i) use ($marca) {
            $producto = Producto::factory()->create(['marca_id' => $marca->id]);

            return Presentacion::factory()->create(['producto_id' => $producto->id, 'precio' => 1000 * $i]);
        });

        Livewire::actingAs($admin)
            ->test(ActualizarPrecios::class)
            ->set('marca_id', $marca->id)
            ->set('porcentaje', 20)
            ->call('aplicarAumento')
            ->assertHasNoErrors();

        foreach ($precios as $i => $p) {
            $this->assertEquals(1000 * ($i + 1) * 1.20, $p->fresh()->precio);
        }
    }
}
