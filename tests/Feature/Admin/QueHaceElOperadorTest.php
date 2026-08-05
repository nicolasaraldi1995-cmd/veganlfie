<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Importador;
use App\Filament\Resources\PedidoResource\Pages\EditPedido;
use App\Filament\Resources\ProductoResource;
use App\Filament\Resources\ProductoResource\Pages\ListProductos;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Pedido;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Hasta dónde llega el empleado, según lo que decidió el dueño: cancela
 * pedidos, no toca precios y no borra el catálogo de una.
 */
class QueHaceElOperadorTest extends TestCase
{
    use RefreshDatabase;

    private function operador(): User
    {
        return User::factory()->create(['role' => 'operador']);
    }

    private function producto(): Producto
    {
        $producto = Producto::factory()->create([
            'marca_id' => Marca::factory()->create()->id,
            'categoria_id' => Categoria::factory()->create()->id,
        ]);

        Presentacion::factory()->create([
            'producto_id' => $producto->id, 'precio' => 5000, 'stock' => 10, 'activo' => true,
        ]);

        return $producto;
    }

    /** Sí: cancelar pedidos es su trabajo. */
    public function test_puede_cancelar_un_pedido(): void
    {
        $pedido = Pedido::factory()->create([
            'user_id' => User::factory()->create(['role' => 'cliente'])->id,
            'estado' => 'pending',
        ]);

        $operador = $this->operador();

        $this->assertTrue(
            $operador->can('update', $pedido),
            'La regla del sistema le dice que no, pero el panel se lo deja hacer igual.'
        );

        Livewire::actingAs($operador)
            ->test(EditPedido::class, ['record' => $pedido->id])
            ->callAction('cancelar');

        $this->assertSame('canceled', $pedido->fresh()->estado);
    }

    /** No: reescribir precios es del dueño, igual que Actualizar precios. */
    public function test_no_entra_al_importador(): void
    {
        $this->actingAs($this->operador());
        $this->assertFalse(Importador::canAccess());

        $this->get('admin/importador')->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->assertTrue(Importador::canAccess());
    }

    /** Pero pasar a pedido lo que mandó un cliente sí, que es otra cosa. */
    public function test_sigue_pudiendo_cargar_un_pedido_desde_archivo(): void
    {
        $this->actingAs($this->operador())
            ->get('admin/cargar-pedido-desde-archivo')
            ->assertOk();
    }

    /** No: borrar el catálogo de una pasada es del dueño. */
    public function test_no_puede_borrar_el_catalogo_en_masa(): void
    {
        $producto = $this->producto();

        Livewire::actingAs($this->operador())
            ->test(ListProductos::class)
            ->assertTableBulkActionHidden('delete');

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(ListProductos::class)
            ->assertTableBulkActionVisible('delete');

        $this->assertNotNull($producto->fresh());
    }

    /** El panel sigue siendo suyo para el trabajo de todos los días. */
    public function test_sigue_entrando_al_catalogo(): void
    {
        $this->actingAs($this->operador());

        $this->assertTrue(ProductoResource::canAccess());
        $this->get('admin/productos')->assertOk();
    }

    /** Y un cliente no monta ningún componente del panel, ni por dentro. */
    public function test_un_cliente_no_monta_los_componentes_del_panel(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cliente']));

        $this->assertFalse(ProductoResource::canAccess());

        // Montar el componente por dentro, salteando la dirección: antes esto
        // respondía como si nada, porque Filament deja vacío el chequeo de
        // acceso de las pantallas de recurso.
        Livewire::test(ListProductos::class)->assertForbidden();
    }
}
