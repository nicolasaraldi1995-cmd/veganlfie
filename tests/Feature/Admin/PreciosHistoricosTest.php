<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\PedidoResource\Pages\EditPedido;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Un pedido de junio se cobró a los precios de junio. Ese número es un hecho
 * cerrado, no una cuenta para rehacer cada vez que alguien abre el pedido.
 *
 * El panel lo rehacía igual: al guardar, a cada renglón le reponía el precio
 * del catálogo de HOY. Al operador le alcanzaba con entrar a un pedido viejo a
 * corregir el domicilio y apretar guardar para reescribir la historia. En la
 * base real eran los 9 renglones de los 4 pedidos: $3.079.670 pasaban a
 * $247.761 sin que nadie tocara un precio.
 *
 * Y si encima la presentación estaba borrada, el precio quedaba en $0.
 */
class PreciosHistoricosTest extends TestCase
{
    use RefreshDatabase;

    /** Lo que se cobró en junio. */
    private const PRECIO_VIEJO = 4253.16;

    /** Lo que vale hoy el mismo producto. */
    private const PRECIO_HOY = 8594.24;

    private Presentacion $presentacion;

    private Pedido $pedido;

    private PedidoItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $producto = Producto::factory()->create([
            'nombre' => 'Aceite de coco en aerosol neutro',
            'marca_id' => Marca::factory()->create()->id,
            'categoria_id' => Categoria::factory()->create()->id,
        ]);

        $this->presentacion = Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'unidad' => '190ml',
            'precio' => self::PRECIO_HOY,
            'stock' => 50,
            'activo' => true,
        ]);

        $this->pedido = Pedido::factory()->create([
            'user_id' => User::factory()->create(['role' => 'cliente'])->id,
            'estado' => 'pending',
            'total' => self::PRECIO_VIEJO * 8,
        ]);

        $this->item = PedidoItem::create([
            'pedido_id' => $this->pedido->id,
            'presentacion_id' => $this->presentacion->id,
            'cantidad' => 8,
            'precio_unitario' => self::PRECIO_VIEJO,
            'subtotal' => self::PRECIO_VIEJO * 8,
        ]);
    }

    private function operador(): User
    {
        return User::factory()->create(['role' => 'operador']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** El caso que rompía: abrir, no tocar nada, guardar. */
    public function test_el_operador_guarda_sin_tocar_nada_y_el_precio_viejo_sigue_ahi(): void
    {
        Livewire::actingAs($this->operador())
            ->test(EditPedido::class, ['record' => $this->pedido->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(self::PRECIO_VIEJO, $this->item->fresh()->precio_unitario, 'Se reescribió el precio con el de hoy.');
        $this->assertEquals(self::PRECIO_VIEJO * 8, $this->item->fresh()->subtotal);
        $this->assertEquals(self::PRECIO_VIEJO * 8, $this->pedido->fresh()->total);
    }

    /** Cambiar algo del pedido tampoco puede arrastrar los precios. */
    public function test_corregir_el_domicilio_no_toca_los_precios(): void
    {
        $componente = Livewire::actingAs($this->operador())
            ->test(EditPedido::class, ['record' => $this->pedido->id]);

        $datos = $componente->get('data');
        $datos['direccion_entrega'] = 'San Nicolás 1255';
        $componente->set('data', $datos)->call('save');

        $this->assertEquals(self::PRECIO_VIEJO, $this->item->fresh()->precio_unitario);
    }

    /**
     * A11: si el producto se borró, el pedido histórico conserva lo que se
     * cobró. Antes quedaba en $0 porque no encontraba la presentación.
     */
    public function test_si_borraron_el_producto_el_pedido_viejo_conserva_su_precio(): void
    {
        $this->presentacion->delete();

        Livewire::actingAs($this->operador())
            ->test(EditPedido::class, ['record' => $this->pedido->id])
            ->call('save');

        $this->assertEquals(self::PRECIO_VIEJO, $this->item->fresh()->precio_unitario, 'El pedido quedó en $0.');
        $this->assertEquals(self::PRECIO_VIEJO * 8, $this->pedido->fresh()->total);
    }

    /** Un renglón nuevo sí toma el precio de hoy: no hay historia que respetar. */
    public function test_un_renglon_nuevo_toma_el_precio_de_hoy(): void
    {
        $otra = Presentacion::factory()->create([
            'producto_id' => $this->presentacion->producto_id,
            'unidad' => '380ml',
            'precio' => 12000,
            'stock' => 10,
            'activo' => true,
        ]);

        $componente = Livewire::actingAs($this->operador())
            ->test(EditPedido::class, ['record' => $this->pedido->id]);

        $datos = $componente->get('data');
        $datos['items']['nuevo'] = [
            'presentacion_id' => $otra->id,
            'cantidad' => 2,
            'precio_unitario' => 1,   // lo que mandaría desde la consola
            'subtotal' => 2,
        ];
        $componente->set('data', $datos)->call('save');

        $agregado = PedidoItem::where('presentacion_id', $otra->id)->firstOrFail();

        $this->assertEquals(12000, $agregado->precio_unitario, 'No tomó el precio del catálogo.');
        $this->assertEquals(24000, $agregado->subtotal);
    }

    /** Si le cambian el producto al renglón, el precio viejo ya no aplica. */
    public function test_cambiar_el_producto_del_renglon_trae_el_precio_del_nuevo(): void
    {
        $otra = Presentacion::factory()->create([
            'producto_id' => $this->presentacion->producto_id,
            'unidad' => '380ml',
            'precio' => 12000,
            'stock' => 10,
            'activo' => true,
        ]);

        $componente = Livewire::actingAs($this->operador())
            ->test(EditPedido::class, ['record' => $this->pedido->id]);

        $datos = $componente->get('data');
        $clave = array_key_first($datos['items']);
        $datos['items'][$clave]['presentacion_id'] = $otra->id;
        $componente->set('data', $datos)->call('save');

        $this->assertEquals(12000, $this->item->fresh()->precio_unitario, 'Se quedó con el precio del producto anterior.');
        $this->assertEquals(12000 * 8, $this->item->fresh()->subtotal);
    }

    /** El freno de siempre: el operador no puede dictar el precio. */
    public function test_el_operador_no_puede_mandar_el_precio_desde_el_navegador(): void
    {
        $componente = Livewire::actingAs($this->operador())
            ->test(EditPedido::class, ['record' => $this->pedido->id]);

        $datos = $componente->get('data');
        $clave = array_key_first($datos['items']);
        $datos['items'][$clave]['precio_unitario'] = 1;
        $datos['items'][$clave]['subtotal'] = 8;
        $componente->set('data', $datos)->call('save');

        $this->assertEquals(self::PRECIO_VIEJO, $this->item->fresh()->precio_unitario);
        $this->assertEquals(self::PRECIO_VIEJO * 8, $this->item->fresh()->subtotal);
    }

    /** Y el dueño sigue pudiendo corregir un precio a mano, que para eso lo ve. */
    public function test_el_admin_si_puede_corregir_un_precio_historico(): void
    {
        $componente = Livewire::actingAs($this->admin())
            ->test(EditPedido::class, ['record' => $this->pedido->id]);

        $datos = $componente->get('data');
        $clave = array_key_first($datos['items']);
        $datos['items'][$clave]['precio_unitario'] = 5000;
        $componente->set('data', $datos)->call('save');

        $this->assertEquals(5000, $this->item->fresh()->precio_unitario);
        $this->assertEquals(40000, $this->item->fresh()->subtotal);
        $this->assertEquals(40000, $this->pedido->fresh()->total);
    }

    /** El admin tampoco pierde el precio por abrir y guardar. */
    public function test_el_admin_guarda_sin_tocar_nada_y_el_precio_viejo_sigue_ahi(): void
    {
        Livewire::actingAs($this->admin())
            ->test(EditPedido::class, ['record' => $this->pedido->id])
            ->call('save');

        $this->assertEquals(self::PRECIO_VIEJO, $this->item->fresh()->precio_unitario);
    }
}
