<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\OfertasMasivas;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProtegerAdminYOfertasTest extends TestCase
{
    use RefreshDatabase;

    // --- #22: oferta masiva limpia el precio de oferta viejo ---------------

    public function test_aplicar_una_oferta_masiva_limpia_el_precio_de_oferta_fijo(): void
    {
        $marca = Marca::factory()->create();
        $producto = Producto::factory()->create(['marca_id' => $marca->id]);
        $p = Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'precio' => 1000,
            'oferta_precio' => 900,   // rebaja fija vieja
            'oferta_porcentaje' => null,
        ]);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(OfertasMasivas::class)
            ->set('marca_id', $marca->id)
            ->set('porcentaje', 50)
            ->call('generarPreview')
            ->call('aplicarOfertas');

        $p->refresh();
        $this->assertNull($p->oferta_precio, 'El precio de oferta fijo le siguió ganando al porcentaje.');
        $this->assertEquals(50, $p->oferta_porcentaje);
        // 1000 con 50% = 500, no los 900 viejos
        $this->assertEquals(500, $p->precio_final);
    }

    // --- #17: no quedarse sin admin ----------------------------------------

    public function test_no_puede_borrarse_a_si_mismo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertTableActionHidden('delete', $admin);
    }

    public function test_puede_borrar_a_otro(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cliente = User::factory()->create(['role' => 'cliente']);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertTableActionVisible('delete', $cliente);
    }

    public function test_el_borrado_en_lote_no_se_lleva_a_los_admins(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otroAdmin = User::factory()->create(['role' => 'admin']);
        $cliente = User::factory()->create(['role' => 'cliente']);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->callTableBulkAction('delete', [$otroAdmin, $cliente]);

        $this->assertNotNull($otroAdmin->fresh(), 'Un admin se borró en lote.');
        $this->assertNotNull($cliente->fresh());
    }

    public function test_el_comando_de_rol_recupera_un_admin_degradado(): void
    {
        $usuario = User::factory()->create(['email' => 'dueno@veganlife.com', 'role' => 'operador']);

        $this->artisan('usuarios:rol dueno@veganlife.com admin')
            ->assertSuccessful();

        $this->assertSame('admin', $usuario->fresh()->role);
    }

    public function test_el_comando_rechaza_un_rol_invalido(): void
    {
        $usuario = User::factory()->create(['role' => 'cliente']);

        $this->artisan('usuarios:rol '.$usuario->email.' jefe')
            ->assertFailed();

        $this->assertSame('cliente', $usuario->fresh()->role);
    }
}
