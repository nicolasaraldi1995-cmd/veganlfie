<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\CargarPedido;
use App\Filament\Resources\PedidoResource\Pages\EditPedido;
use App\Filament\Resources\ProductoResource\Pages\EditProducto;
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
 * Al operador se le esconden los campos de plata, pero esconder no es impedir:
 * el estado del formulario viaja al navegador y vuelve, así que desde la
 * consola se podía hacer $wire.set(...) y mandar el precio que uno quisiera.
 * Eran tres puertas distintas y las tres escribían en la base sin chistar.
 */
class OperadorNoTocaPreciosTest extends TestCase
{
    use RefreshDatabase;

    private const PRECIO = 80000.0;

    private function presentacion(): Presentacion
    {
        $producto = Producto::factory()->create([
            'nombre' => 'Producto caro',
            'marca_id' => Marca::factory()->create()->id,
            'categoria_id' => Categoria::factory()->create()->id,
        ]);

        return Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'unidad' => '1u',
            'precio' => self::PRECIO,
            'precio_costo' => 30000,
            'margen_porcentaje' => 40,
            'stock' => 100,
            'activo' => true,
        ]);
    }

    private function operador(): User
    {
        return User::factory()->create(['role' => 'operador']);
    }

    /** Puerta 1: la pantalla de cargar un pedido. */
    public function test_no_puede_cargar_un_pedido_con_el_precio_que_quiera(): void
    {
        $presentacion = $this->presentacion();
        $cliente = User::factory()->create(['role' => 'cliente']);

        Livewire::actingAs($this->operador())
            ->test(CargarPedido::class)
            ->set('cliente_id', (string) $cliente->id)
            ->set('items', [
                $presentacion->id => [
                    'presentacion_id' => $presentacion->id,
                    'cantidad' => 3,
                    'precio' => 1.0,   // lo que mandaría desde la consola
                    'nombre' => 'Producto caro',
                    'unidad' => '1u',
                    'marca' => 'Marca',
                ],
            ])
            ->call('crearPedido');

        $item = PedidoItem::firstOrFail();

        $this->assertEquals(self::PRECIO, $item->precio_unitario, 'Se guardó el precio que mandó el navegador.');
        $this->assertEquals(self::PRECIO * 3, $item->subtotal);
        $this->assertEquals(self::PRECIO * 3, Pedido::firstOrFail()->total);
    }

    /** Puerta 2: editar un pedido que ya existe. */
    public function test_no_puede_reescribir_el_precio_de_un_pedido(): void
    {
        $presentacion = $this->presentacion();
        $pedido = Pedido::factory()->create([
            'user_id' => User::factory()->create(['role' => 'cliente'])->id,
            'estado' => 'pending',
        ]);
        $item = PedidoItem::create([
            'pedido_id' => $pedido->id,
            'presentacion_id' => $presentacion->id,
            'cantidad' => 2,
            'precio_unitario' => self::PRECIO,
            'subtotal' => self::PRECIO * 2,
        ]);

        $componente = Livewire::actingAs($this->operador())
            ->test(EditPedido::class, ['record' => $pedido->id]);

        $datos = $componente->get('data');
        $clave = array_key_first($datos['items']);
        $datos['items'][$clave]['precio_unitario'] = 1;
        $datos['items'][$clave]['subtotal'] = 2;

        $componente->set('data', $datos)->call('save');

        $this->assertEquals(self::PRECIO, $item->fresh()->precio_unitario);
        $this->assertEquals(self::PRECIO * 2, $item->fresh()->subtotal);
        $this->assertEquals(self::PRECIO * 2, $pedido->fresh()->total);
    }

    /** Puerta 3: el precio público del catálogo. */
    public function test_no_puede_cambiar_el_precio_publico_de_un_producto(): void
    {
        $presentacion = $this->presentacion();

        $componente = Livewire::actingAs($this->operador())
            ->test(EditProducto::class, ['record' => $presentacion->producto_id]);

        $datos = $componente->get('data');
        $clave = array_key_first($datos['presentaciones']);
        $datos['presentaciones'][$clave]['precio'] = 1;
        $datos['presentaciones'][$clave]['precio_costo'] = 1;

        $componente->set('data', $datos)->call('save');

        $this->assertEquals(self::PRECIO, $presentacion->fresh()->precio);
        $this->assertEquals(30000, $presentacion->fresh()->precio_costo);
        $this->assertEquals(40, $presentacion->fresh()->margen_porcentaje);
    }

    /** Y el dueño sigue pudiendo poner el precio que quiera, que para eso es. */
    public function test_el_admin_si_puede_cambiar_el_precio(): void
    {
        $presentacion = $this->presentacion();

        $componente = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(EditProducto::class, ['record' => $presentacion->producto_id]);

        $datos = $componente->get('data');
        $clave = array_key_first($datos['presentaciones']);
        $datos['presentaciones'][$clave]['precio'] = 12345;

        $componente->set('data', $datos)->call('save');

        $this->assertEquals(12345, $presentacion->fresh()->precio);
    }
}
