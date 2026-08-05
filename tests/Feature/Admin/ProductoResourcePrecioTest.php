<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ProductoResource\Pages\EditProducto;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductoResourcePrecioTest extends TestCase
{
    use RefreshDatabase;

    public function test_costo_descuento_margen_e_iva_calculan_el_precio_solos(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $producto = Producto::factory()->create(['marca_id' => Marca::factory()->create()->id]);
        $presentacion = Presentacion::factory()->create(['producto_id' => $producto->id]);

        Livewire::actingAs($admin)
            ->test(EditProducto::class, ['record' => $producto->id])
            ->set('data.presentaciones.0.precio_costo', 1000)
            ->set('data.presentaciones.0.descuento_porcentaje', 15)
            ->set('data.presentaciones.0.margen_porcentaje', 75)
            ->set('data.presentaciones.0.iva', true)
            ->assertSet('data.presentaciones.0.precio', 1799.88);
    }

    public function test_la_oferta_se_recalcula_si_el_costo_cambia_despues_de_cargarla(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $producto = Producto::factory()->create(['marca_id' => Marca::factory()->create()->id]);
        $presentacion = Presentacion::factory()->create(['producto_id' => $producto->id]);

        $component = Livewire::actingAs($admin)->test(EditProducto::class, ['record' => $producto->id]);

        // Se carga costo/margen y, con el precio ya calculado, una oferta del 10%.
        $component->set('data.presentaciones.0.precio_costo', 1000)
            ->set('data.presentaciones.0.margen_porcentaje', 100)
            ->assertSet('data.presentaciones.0.precio', 2000)
            ->set('data.presentaciones.0.oferta_porcentaje', 10)
            ->assertSet('data.presentaciones.0.oferta_precio', 1800);

        // Si después se sube el margen, el precio de oferta viejo (1800, calculado
        // sobre 2000) debe actualizarse -- no quedar pisando el precio nuevo.
        $component->set('data.presentaciones.0.margen_porcentaje', 150)
            ->assertSet('data.presentaciones.0.precio', 2500)
            ->assertSet('data.presentaciones.0.oferta_precio', 2250);
    }

    public function test_no_se_puede_cargar_un_precio_de_oferta_mayor_o_igual_al_precio(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $producto = Producto::factory()->create(['marca_id' => Marca::factory()->create()->id]);
        Presentacion::factory()->create(['producto_id' => $producto->id, 'precio' => 1000]);

        Livewire::actingAs($admin)
            ->test(EditProducto::class, ['record' => $producto->id])
            ->set('data.presentaciones.0.oferta_precio', 1000)
            ->call('save')
            ->assertHasErrors(['data.presentaciones.0.oferta_precio']);
    }
}
