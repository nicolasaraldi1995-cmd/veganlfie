<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockFugaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cambiar_el_producto_de_un_item_mueve_la_reserva_de_stock(): void
    {
        Configuracion::actual()->update(['controlar_stock' => true]);

        $viejo = Presentacion::factory()->create(['stock' => 10, 'precio' => 100]);
        $nuevo = Presentacion::factory()->create(['stock' => 10, 'precio' => 100]);
        $cliente = User::factory()->create(['role' => 'cliente']);
        $pedido = Pedido::factory()->create(['user_id' => $cliente->id, 'estado' => 'pending']);

        $item = DB::transaction(fn () => PedidoItem::create([
            'pedido_id' => $pedido->id,
            'presentacion_id' => $viejo->id,
            'cantidad' => 3,
            'precio_unitario' => 100,
            'subtotal' => 300,
        ]));

        // Se reservaron 3 del producto viejo.
        $this->assertEquals(7, $viejo->fresh()->stock);
        $this->assertEquals(10, $nuevo->fresh()->stock);

        // El panel permite cambiar el producto de un renglón ya cargado.
        DB::transaction(fn () => $item->update(['presentacion_id' => $nuevo->id]));

        // Las 3 unidades tienen que volver al viejo y descontarse del nuevo.
        $this->assertEquals(10, $viejo->fresh()->stock, 'El stock del producto original quedó reservado para siempre.');
        $this->assertEquals(7, $nuevo->fresh()->stock, 'Al producto nuevo nunca se le descontó el stock.');
    }
}
