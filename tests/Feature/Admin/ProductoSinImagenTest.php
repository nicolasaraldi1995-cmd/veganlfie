<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ProductoResource\Pages\ListProductos;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductoSinImagenTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_filtro_sin_foto_deja_solo_los_productos_sin_imagen(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $marca = Marca::factory()->create();

        $conFoto = Producto::factory()->create(['marca_id' => $marca->id, 'imagen' => 'productos/foto.jpg']);
        $sinFoto = Producto::factory()->create(['marca_id' => $marca->id, 'imagen' => null]);
        // El importador deja el campo vacío en vez de nulo: también cuenta como sin foto.
        $vacio = Producto::factory()->create(['marca_id' => $marca->id, 'imagen' => '']);

        Livewire::actingAs($admin)
            ->test(ListProductos::class)
            ->assertCanSeeTableRecords([$conFoto, $sinFoto, $vacio])
            ->filterTable('sin_imagen')
            ->assertCanSeeTableRecords([$sinFoto, $vacio])
            ->assertCanNotSeeTableRecords([$conFoto]);
    }
}
