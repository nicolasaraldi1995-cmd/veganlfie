<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\OfertasMasivas;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OfertasMasivasTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_porcentaje_fuera_de_rango_es_rechazado_y_no_se_guarda(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $marca = Marca::factory()->create();
        $producto = Producto::factory()->create(['marca_id' => $marca->id]);
        $presentacion = Presentacion::factory()->create(['producto_id' => $producto->id, 'precio' => 1000]);

        Livewire::actingAs($admin)
            ->test(OfertasMasivas::class)
            ->set('aplicar_a', 'marca')
            ->set('marca_id', $marca->id)
            ->set('porcentaje', 150)
            ->call('aplicarOfertas')
            ->assertHasErrors(['porcentaje']);

        $this->assertNull($presentacion->fresh()->oferta_porcentaje);
    }

    public function test_un_porcentaje_valido_aplica_la_oferta_a_toda_la_marca(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $marca = Marca::factory()->create();
        $producto = Producto::factory()->create(['marca_id' => $marca->id]);
        $presentacion = Presentacion::factory()->create(['producto_id' => $producto->id, 'precio' => 1000]);

        Livewire::actingAs($admin)
            ->test(OfertasMasivas::class)
            ->set('aplicar_a', 'marca')
            ->set('marca_id', $marca->id)
            ->set('porcentaje', 20)
            ->call('aplicarOfertas')
            ->assertHasNoErrors();

        $this->assertEquals(20, $presentacion->fresh()->oferta_porcentaje);
    }
}
