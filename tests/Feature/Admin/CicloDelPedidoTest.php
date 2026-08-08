<?php

namespace Tests\Feature\Admin;

use App\Mail\PedidoEstadoMail;
use App\Models\Categoria;
use App\Models\Configuracion;
use App\Models\Marca;
use App\Models\Pedido;
use App\Models\PedidoItem;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * El stock y el mail del ciclo del pedido, pasen por donde pasen. Antes vivían
 * sólo en la acción de la tabla: el desplegable de estado cambiaba la palabra y
 * nada más, y cancelar dos veces inflaba el stock.
 */
class CicloDelPedidoTest extends TestCase
{
    use RefreshDatabase;

    private Presentacion $presentacion;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $producto = Producto::factory()->create([
            'marca_id' => Marca::factory()->create()->id,
            'categoria_id' => Categoria::factory()->create()->id,
            'activo' => true,
        ]);

        $this->presentacion = Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'unidad' => '1u',
            'precio' => 1000,
            'stock' => 20,
            'activo' => true,
        ]);
    }

    /** Un pedido de 5 unidades: el observer de items ya reservó al crearlo. */
    private function pedido(string $estado = 'pending', int $cantidad = 5): Pedido
    {
        $pedido = Pedido::factory()->create([
            'user_id' => User::factory()->create(['role' => 'cliente'])->id,
            'estado' => $estado,
            'datos_cliente' => ['nombre' => 'Cliente', 'email' => 'cliente@ejemplo.com'],
        ]);

        PedidoItem::create([
            'pedido_id' => $pedido->id,
            'presentacion_id' => $this->presentacion->id,
            'cantidad' => $cantidad,
            'precio_unitario' => 1000,
            'subtotal' => 5000,
        ]);

        return $pedido;
    }

    private function stock(): int
    {
        return (int) $this->presentacion->fresh()->stock;
    }

    /** Cancelar por el desplegable (no por el botón) devuelve el stock. */
    public function test_cancelar_cambiando_el_estado_devuelve_el_stock(): void
    {
        $pedido = $this->pedido();          // reservó 5: stock 20 → 15
        $this->assertSame(15, $this->stock());

        $pedido->update(['estado' => 'canceled']);

        $this->assertSame(20, $this->stock(), 'No devolvió el stock al cancelar.');
    }

    /** El bug del inflado: cancelar, revivir, cancelar no puede inventar stock. */
    public function test_cancelar_revivir_cancelar_no_infla_el_stock(): void
    {
        $pedido = $this->pedido();          // stock 15

        $pedido->update(['estado' => 'canceled']);   // devuelve: 20
        $this->assertSame(20, $this->stock());

        $pedido->update(['estado' => 'pending']);    // reserva de nuevo: 15
        $this->assertSame(15, $this->stock(), 'Revivir no volvió a reservar.');

        $pedido->update(['estado' => 'canceled']);   // devuelve: 20
        $this->assertSame(20, $this->stock(), 'El stock quedó inflado.');
    }

    /** Editar un renglón de un pedido ya cancelado no vuelve a mover el stock. */
    public function test_editar_un_pedido_cancelado_no_devuelve_stock_de_nuevo(): void
    {
        $pedido = $this->pedido();
        $pedido->update(['estado' => 'canceled']);   // stock 20
        $this->assertSame(20, $this->stock());

        $item = $pedido->items()->first();
        $item->delete();                              // no debería tocar el stock

        $this->assertSame(20, $this->stock(), 'Borrar un renglón de un cancelado infló el stock.');
    }

    /** Confirmar (que no cruza la línea de cancelado) no toca el stock. */
    public function test_confirmar_no_toca_el_stock(): void
    {
        $pedido = $this->pedido();          // stock 15
        $pedido->update(['estado' => 'confirmed']);

        $this->assertSame(15, $this->stock());
    }

    /** El mail sale por cualquier cambio de estado, no sólo desde la tabla. */
    public function test_cambiar_el_estado_manda_el_mail_al_cliente(): void
    {
        $pedido = $this->pedido();
        $pedido->update(['estado' => 'confirmed']);

        Mail::assertSent(PedidoEstadoMail::class, fn ($mail) => $mail->hasTo('cliente@ejemplo.com'));
    }

    /** Guardar el pedido sin cambiar el estado no manda mail ni toca stock. */
    public function test_guardar_sin_cambiar_el_estado_no_hace_nada(): void
    {
        $pedido = $this->pedido();
        Mail::fake();  // limpia lo del setup

        $pedido->update(['datos_cliente' => ['nombre' => 'Otro']]);

        $this->assertSame(15, $this->stock());
        Mail::assertNothingSent();
    }

    /** Reservar de nuevo al revivir respeta el conteo aunque no haya stock físico. */
    public function test_revivir_reserva_aunque_el_stock_quede_bajo(): void
    {
        Configuracion::actual()->update(['controlar_stock' => true]);

        $pedido = $this->pedido('canceled', 5);   // cancelado: el item reservó al crear, pero...
        // Al crear el item sobre un pedido ya 'canceled', el observer de item
        // igual reserva (mira el pedido nuevo). Normalizamos el escenario:
        $this->presentacion->update(['stock' => 3]);

        $pedido->update(['estado' => 'pending']);

        // Reservó 5 sobre 3: queda en -2. El conteo manda; el admin lo ve.
        $this->assertSame(-2, $this->stock());
    }
}
