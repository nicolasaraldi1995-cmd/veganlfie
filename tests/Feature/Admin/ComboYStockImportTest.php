<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Importador;
use App\Filament\Resources\ComboResource\Pages\EditCombo;
use App\Models\Combo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ComboYStockImportTest extends TestCase
{
    use RefreshDatabase;

    // --- #19: el precio del combo ------------------------------------------

    /**
     * El bug principal: se pasa de precio manual a descuento y el precio manual
     * viejo queda escondido. Como le gana al porcentaje, el combo seguía
     * cobrando el precio de antes.
     */
    public function test_pasar_de_manual_a_descuento_limpia_el_precio_manual(): void
    {
        $combo = Combo::create([
            'nombre' => 'Combo', 'slug' => 'combo', 'precio_manual' => 700, 'activo' => true,
        ]);

        $componente = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(EditCombo::class, ['record' => $combo->id]);

        $datos = $componente->get('data');
        $datos['tipo_precio'] = 'descuento';
        $datos['descuento_porcentaje'] = 20;
        $componente->set('data', $datos)->call('save');

        $this->assertNull($combo->fresh()->precio_manual, 'El precio manual viejo quedó escondido.');
        $this->assertEquals(20, $combo->fresh()->descuento_porcentaje);
    }

    /** El precio manual cargado se respeta. */
    public function test_un_precio_manual_cargado_manda(): void
    {
        $combo = Combo::create([
            'nombre' => 'Combo', 'slug' => 'combo-2', 'precio_manual' => 1500, 'activo' => true,
        ]);

        $this->assertSame(1500.0, $combo->precio);
    }

    /** Sin precio manual ni descuento, es la suma de sus productos. */
    public function test_sin_precio_manual_usa_el_descuento(): void
    {
        $combo = Combo::create([
            'nombre' => 'Combo', 'slug' => 'combo-3',
            'precio_manual' => null, 'descuento_porcentaje' => null, 'activo' => true,
        ]);

        $this->assertSame($combo->precio_calculado, $combo->precio);
    }

    // --- #23: "cantidad" ya no se mapea a stock ----------------------------

    public function test_una_columna_cantidad_no_se_mapea_a_stock(): void
    {
        $page = new Importador;
        $page->headers = ['Nombre', 'Marca', 'Cantidad', 'Precio'];

        $metodo = new \ReflectionMethod($page, 'autoMapColumns');
        $metodo->invoke($page);

        $this->assertSame('', $page->columnMap['stock'], '"Cantidad" se mapeó a stock.');
    }

    public function test_una_columna_stock_si_se_mapea(): void
    {
        $page = new Importador;
        $page->headers = ['Nombre', 'Marca', 'Stock', 'Precio'];

        (new \ReflectionMethod($page, 'autoMapColumns'))->invoke($page);

        $this->assertSame('Stock', $page->columnMap['stock']);
    }
}
