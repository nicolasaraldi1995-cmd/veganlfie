<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ComboResource\Pages\CreateCombo;
use App\Filament\Resources\ComboResource\Pages\EditCombo;
use App\Filament\Resources\MarcaResource\Pages\EditMarca;
use App\Filament\Resources\PedidoResource\Pages\EditPedido;
use App\Filament\Resources\ProductoResource\Pages\EditProducto;
use App\Models\Categoria;
use App\Models\Combo;
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
 * Esconder un campo del formulario no impide que el valor viaje. Filament llena
 * los formularios con attributesToArray(), y el recorte estaba puesto en
 * toArray(): el costo y el margen llegaban igual al navegador del operador,
 * dentro del wire:snapshot. Se leían con ver-código-fuente, sin consola.
 */
class CostosNoLleganAlOperadorTest extends TestCase
{
    use RefreshDatabase;

    private const COSTO = 3333.33;

    private const MARGEN = 62.5;

    private function catalogo(): Presentacion
    {
        $marca = Marca::factory()->create([
            'nombre' => 'Marca con margen',
            'margen_porcentaje' => self::MARGEN,
            'descuento_porcentaje' => 17.5,
        ]);

        $producto = Producto::factory()->create([
            'marca_id' => $marca->id,
            'categoria_id' => Categoria::factory()->create()->id,
        ]);

        return Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'precio' => 10000,
            'precio_costo' => self::COSTO,
            'margen_porcentaje' => self::MARGEN,
            'descuento_porcentaje' => 17.5,
            'stock' => 5,
            'activo' => true,
        ]);
    }

    private function operador(): User
    {
        return User::factory()->create(['role' => 'operador']);
    }

    public function test_el_costo_no_viaja_en_la_pantalla_de_editar_producto(): void
    {
        $presentacion = $this->catalogo();

        $html = Livewire::actingAs($this->operador())
            ->test(EditProducto::class, ['record' => $presentacion->producto_id])
            ->html();

        // Se mira el valor, no el nombre del campo: la clave vacía queda porque
        // el formulario la declara, y vacía no dice nada.
        foreach (['3333.33', '3333', '62.50', '17.50'] as $numero) {
            $this->assertStringNotContainsString($numero, $html, "El número {$numero} llegó al navegador del operador.");
        }

        $this->assertStringContainsString('precio_costo&quot;:null', $html, 'Tendría que llegar vacío, no ausente.');
    }

    public function test_el_margen_no_viaja_en_la_pantalla_de_editar_marca(): void
    {
        $presentacion = $this->catalogo();

        $html = Livewire::actingAs($this->operador())
            ->test(EditMarca::class, ['record' => $presentacion->producto->marca_id])
            ->html();

        // Los números sueltos aparecen en cualquier lado (medidas de CSS, un
        // trazo de un icono), así que se mira el par clave-valor del estado.
        foreach (['margen_porcentaje', 'descuento_porcentaje'] as $campo) {
            $this->assertStringContainsString("{$campo}&quot;:null", $html, "«{$campo}» llegó con valor al navegador del operador.");
        }

        $this->assertStringNotContainsString('62.50', $html);
    }

    /**
     * En la ficha del pedido pasaba lo mismo con el total y los importes de
     * cada ítem: escondidos en pantalla, presentes en el estado.
     */
    public function test_el_total_del_pedido_no_viaja_en_la_pantalla_de_editarlo(): void
    {
        $presentacion = $this->catalogo();
        $pedido = Pedido::factory()->create([
            'user_id' => User::factory()->create(['role' => 'cliente'])->id,
            'estado' => 'pending',
        ]);
        PedidoItem::create([
            'pedido_id' => $pedido->id,
            'presentacion_id' => $presentacion->id,
            'cantidad' => 2,
            'precio_unitario' => 10000,
            'subtotal' => 20000,
        ]);
        $pedido->recalcularTotal();

        $html = Livewire::actingAs($this->operador())
            ->test(EditPedido::class, ['record' => $pedido->id])
            ->html();

        foreach (['20000.00', '10000.00'] as $importe) {
            $this->assertStringNotContainsString($importe, $html, "El importe {$importe} llegó al navegador del operador.");
        }
    }

    /** Al dueño sí, que para eso los edita. */
    public function test_el_admin_sigue_viendo_costo_y_margen(): void
    {
        $presentacion = $this->catalogo();

        $html = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(EditProducto::class, ['record' => $presentacion->producto_id])
            ->html();

        $this->assertStringContainsString('3333.33', $html);
    }

    /**
     * Con abrir "Crear combo" el operador se llevaba el catálogo con precios,
     * porque el desplegable de presentaciones los imprimía en cada opción.
     */
    public function test_el_desplegable_de_combos_no_le_lista_los_precios(): void
    {
        $this->catalogo();

        $html = Livewire::actingAs($this->operador())->test(CreateCombo::class)->html();

        $this->assertStringNotContainsString('10000', $html, 'El precio salió en el desplegable.');
    }

    /**
     * El precio del combo se publica en la web tal cual. Ponerlo es del dueño,
     * igual que Actualizar precios y Ofertas masivas.
     */
    public function test_el_operador_no_puede_ponerle_precio_a_un_combo(): void
    {
        $combo = Combo::create(['nombre' => 'Combo testigo', 'activo' => true]);

        $componente = Livewire::actingAs($this->operador())
            ->test(EditCombo::class, ['record' => $combo->id]);

        $datos = $componente->get('data');
        $datos['tipo_precio'] = 'manual';
        $datos['precio_manual'] = 1;
        $datos['descuento_porcentaje'] = 90;

        $componente->set('data', $datos)->call('save');

        $this->assertNull($combo->fresh()->precio_manual, 'Le puso precio fijo al combo.');
        $this->assertNull($combo->fresh()->descuento_porcentaje);
    }
}
