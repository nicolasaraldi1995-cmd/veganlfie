<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\CargarPedidoDesdeArchivo;
use App\Models\Pedido;
use App\Models\Presentacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El cliente arma el pedido en la lista de precios que se le manda por
 * WhatsApp y devuelve un .json; esto lo convierte en un pedido real sin
 * tipearlo a mano.
 */
class CargarPedidoDesdeArchivoTest extends TestCase
{
    use RefreshDatabase;

    private function archivo(array $items, string $negocio = 'Kiosco El Sol'): UploadedFile
    {
        $json = json_encode([
            'veganlife_pedido' => 1,
            'negocio' => $negocio,
            'fecha' => '2026-08-03',
            'items' => $items,
        ]);

        return UploadedFile::fake()->createWithContent('pedido.json', (string) $json);
    }

    public function test_crea_el_pedido_con_lo_que_cargo_el_cliente(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cliente = User::factory()->create(['role' => 'cliente', 'name' => 'Kiosco El Sol']);
        $a = Presentacion::factory()->create(['precio' => 1000, 'stock' => 50, 'activo' => true]);
        $b = Presentacion::factory()->create(['precio' => 2500, 'stock' => 50, 'activo' => true]);

        Livewire::actingAs($admin)
            ->test(CargarPedidoDesdeArchivo::class)
            ->set('cliente_id', (string) $cliente->id)
            ->set('archivo', [$this->archivo([
                ['id' => $a->id, 'cantidad' => 3],
                ['id' => $b->id, 'cantidad' => 2],
            ])])
            ->call('confirmar');

        $pedido = Pedido::where('user_id', $cliente->id)->first();

        $this->assertNotNull($pedido);
        $this->assertEquals('pending', $pedido->estado);
        $this->assertCount(2, $pedido->items);
        // 3 x 1000 + 2 x 2500 = 8000
        $this->assertEquals(8000, $pedido->total);
    }

    public function test_usa_el_precio_de_hoy_y_no_el_que_traiga_el_archivo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cliente = User::factory()->create(['role' => 'cliente']);
        $pres = Presentacion::factory()->create(['precio' => 1500, 'stock' => 50, 'activo' => true]);

        Livewire::actingAs($admin)
            ->test(CargarPedidoDesdeArchivo::class)
            ->set('cliente_id', (string) $cliente->id)
            // El archivo dice otro precio: el que vale es el de la base.
            ->set('archivo', [$this->archivo([
                ['id' => $pres->id, 'cantidad' => 2, 'precio' => 99],
            ])])
            ->call('confirmar');

        $pedido = Pedido::where('user_id', $cliente->id)->first();

        $this->assertEquals(1500, $pedido->items->first()->precio_unitario);
        $this->assertEquals(3000, $pedido->total);
    }

    public function test_descarta_productos_que_ya_no_existen(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cliente = User::factory()->create(['role' => 'cliente']);
        $pres = Presentacion::factory()->create(['precio' => 1000, 'stock' => 50, 'activo' => true]);

        Livewire::actingAs($admin)
            ->test(CargarPedidoDesdeArchivo::class)
            ->set('cliente_id', (string) $cliente->id)
            ->set('archivo', [$this->archivo([
                ['id' => $pres->id, 'cantidad' => 1],
                ['id' => 999999, 'cantidad' => 5],
            ])])
            ->call('confirmar');

        $pedido = Pedido::where('user_id', $cliente->id)->first();

        $this->assertCount(1, $pedido->items);
    }

    public function test_un_archivo_que_no_es_un_pedido_no_crea_nada(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cliente = User::factory()->create(['role' => 'cliente']);

        Livewire::actingAs($admin)
            ->test(CargarPedidoDesdeArchivo::class)
            ->set('cliente_id', (string) $cliente->id)
            ->set('archivo', [UploadedFile::fake()->createWithContent('cualquiera.json', 'esto no es json')])
            ->call('confirmar');

        $this->assertEquals(0, Pedido::count());
    }

    public function test_un_cliente_no_puede_entrar_a_esta_pantalla(): void
    {
        $cliente = User::factory()->create(['role' => 'cliente']);

        $this->actingAs($cliente)->get(CargarPedidoDesdeArchivo::getUrl())->assertForbidden();
    }
}
