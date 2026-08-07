<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\PrecioResource\Pages\ListPrecios;
use App\Filament\Resources\ProductoResource\Pages\EditProducto;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El margen y el descuento se cargan una vez en la marca y sus productos los
 * toman prestados. La idea es no escribir el mismo 30% 2161 veces.
 *
 * Prestados y no copiados: si al abrir un producto se le grabara el número de
 * la marca, ese producto quedaría clavado en 30% y cambiar la marca ya no lo
 * movería. Eso es lo que hacía el formulario y es lo que estas pruebas fijan.
 */
class MargenDeLaMarcaTest extends TestCase
{
    use RefreshDatabase;

    private Marca $marca;

    private Presentacion $presentacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->marca = Marca::factory()->create([
            'nombre' => 'Oralí',
            'margen_porcentaje' => 30,
            'descuento_porcentaje' => 10,
            'activo' => true,
        ]);

        $producto = Producto::factory()->create([
            'nombre' => 'Aceite de oliva',
            'marca_id' => $this->marca->id,
            'categoria_id' => Categoria::factory()->create()->id,
            'activo' => true,
        ]);

        $this->presentacion = Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'unidad' => '500ml',
            'precio' => 1000,
            'precio_costo' => null,
            'margen_porcentaje' => null,
            'descuento_porcentaje' => null,
            'iva' => false,
            'activo' => true,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    // --- El préstamo -----------------------------------------------------

    public function test_sin_valor_propio_toma_el_de_la_marca(): void
    {
        $this->assertSame(30.0, $this->presentacion->margenEfectivo());
        $this->assertSame(10.0, $this->presentacion->descuentoEfectivo());
    }

    public function test_el_valor_propio_le_gana_al_de_la_marca(): void
    {
        $this->presentacion->update(['margen_porcentaje' => 45]);

        $this->assertSame(45.0, $this->presentacion->fresh()->margenEfectivo());
    }

    /** Cero es un margen válido, no "vacío". */
    public function test_un_margen_de_cero_no_se_confunde_con_vacio(): void
    {
        $this->presentacion->update(['margen_porcentaje' => 0]);

        $this->assertSame(0.0, $this->presentacion->fresh()->margenEfectivo());
    }

    public function test_si_la_marca_tampoco_lo_tiene_queda_en_nada(): void
    {
        $this->marca->update(['margen_porcentaje' => null]);

        $this->assertNull($this->presentacion->fresh()->margenEfectivo());
    }

    /** Lo que hace que valga la pena: se cambia una vez y cambian todos. */
    public function test_cambiar_la_marca_mueve_a_todos_sus_productos(): void
    {
        $this->marca->update(['margen_porcentaje' => 35]);

        $this->assertSame(35.0, $this->presentacion->fresh()->margenEfectivo());
    }

    // --- Que se use para la cuenta ---------------------------------------

    /** Cargar el costo tiene que alcanzar: el margen ya lo pone la marca. */
    public function test_cargar_el_costo_calcula_el_precio_con_el_margen_de_la_marca(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ListPrecios::class)
            ->call('updateTableColumnState', 'precio_costo', (string) $this->presentacion->id, '1000');

        // 1000 menos 10% de descuento = 900, más 30% de margen = 1170
        $this->assertEquals(1170, $this->presentacion->fresh()->precio);
    }

    public function test_el_margen_propio_le_gana_al_calcular(): void
    {
        $this->presentacion->update(['margen_porcentaje' => 50]);

        Livewire::actingAs($this->admin())
            ->test(ListPrecios::class)
            ->call('updateTableColumnState', 'precio_costo', (string) $this->presentacion->id, '1000');

        // 1000 menos 10% = 900, más 50% propio = 1350
        $this->assertEquals(1350, $this->presentacion->fresh()->precio);
    }

    /**
     * Lo que él pidió con todas las letras: cargar 30% en la marca una vez y
     * que el número aparezca en la fila de cada producto de esa marca.
     */
    public function test_el_numero_de_la_marca_se_ve_en_la_fila_del_producto(): void
    {
        $html = Livewire::actingAs($this->admin())
            ->test(ListPrecios::class)
            ->assertCanSeeTableRecords([$this->presentacion])
            ->html();

        $this->assertStringContainsString('placeholder="30"', $html, 'No se ve el margen de la marca.');
        $this->assertStringContainsString('placeholder="10"', $html, 'No se ve el descuento de la marca.');
    }

    /** Con decimales tiene que quedar legible, no "12,50". */
    public function test_el_numero_prestado_se_muestra_sin_ceros_de_relleno(): void
    {
        $this->marca->update(['margen_porcentaje' => 12.5]);

        $html = Livewire::actingAs($this->admin())->test(ListPrecios::class)->html();

        $this->assertStringContainsString('placeholder="12,5"', $html);
    }

    // --- Y lo que NO tiene que pasar --------------------------------------

    /**
     * El bug que se está arreglando: abrir un producto y guardarlo sin tocar
     * nada le grababa el número de la marca como propio, y a partir de ahí
     * dejaba de seguirla.
     */
    public function test_abrir_y_guardar_un_producto_no_le_graba_el_valor_de_la_marca(): void
    {
        Livewire::actingAs($this->admin())
            ->test(EditProducto::class, ['record' => $this->presentacion->producto_id])
            ->call('save')
            ->assertHasNoErrors();

        $fresca = $this->presentacion->fresh();

        $this->assertNull($fresca->margen_porcentaje, 'Se le grabó el margen de la marca como propio.');
        $this->assertNull($fresca->descuento_porcentaje);
        $this->assertSame(30.0, $fresca->margenEfectivo(), 'Y encima dejó de seguir a la marca.');
    }

    /**
     * Cargar el margen en la marca no puede tocar ni un precio: los precios de
     * hoy vienen de la lista del proveedor, no de esta cuenta. Se aplica cuando
     * alguien carga un costo, no antes.
     */
    public function test_poner_el_margen_en_la_marca_no_reescribe_ningun_precio(): void
    {
        $this->marca->update(['margen_porcentaje' => 80]);

        $this->assertEquals(1000, $this->presentacion->fresh()->precio, 'Se recalculó el catálogo solo.');
    }

    /** El operador no ve nada de esto, ni siquiera prestado. */
    public function test_el_operador_no_entra_a_la_pantalla_de_precios(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'operador']))
            ->test(ListPrecios::class)
            ->assertForbidden();
    }
}
