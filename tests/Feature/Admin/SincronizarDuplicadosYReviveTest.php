<?php

namespace Tests\Feature\Admin;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Services\ProductImportService;
use App\Services\SincronizarCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SincronizarDuplicadosYReviveTest extends TestCase
{
    use RefreshDatabase;

    private function producto(string $nombre, string $marca, array $extra = []): Producto
    {
        $producto = Producto::create(array_merge([
            'nombre' => $nombre,
            'marca_id' => Marca::firstOrCreate(['nombre' => $marca])->id,
            'categoria_id' => Categoria::firstOrCreate(['nombre' => 'Prueba'])->id,
            'activo' => true,
        ], $extra));

        Presentacion::create([
            'producto_id' => $producto->id, 'unidad' => '1u', 'precio' => 100, 'stock' => 1, 'activo' => true,
        ]);

        return $producto;
    }

    private function lista(array $filas): string
    {
        $html = '<table>'.str_repeat('<tr><td>x</td></tr>', 4)
            .'<tr><td>Nombre</td><td>Marca</td><td>Categoría</td><td>Unidad</td><td>Precio</td></tr>';
        foreach ($filas as [$n, $m]) {
            $html .= "<tr><td>{$n}</td><td>{$m}</td><td>Prueba</td><td>1u</td><td>150,00</td></tr>";
        }
        $ruta = tempnam(sys_get_temp_dir(), 'lista_').'.xls';
        file_put_contents($ruta, $html.'</table>');

        return $ruta;
    }

    // --- #9: unificarDuplicados sólo toca lo que se movió ------------------

    /** Un duplicado preexistente que la sincronización no tocó se deja en paz. */
    public function test_no_apaga_un_duplicado_ajeno_a_la_sincronizacion(): void
    {
        // Dos productos iguales que ya estaban así, a propósito. Están en la
        // lista, así que no se dan de baja; lo que se prueba es que
        // unificarDuplicados no los toque por ser ajenos al cambio.
        $a = $this->producto('Barra proteica', 'FitMarket');
        $b = $this->producto('Barra proteica', 'FitMarket');
        // Y uno de otra marca que la lista mueve.
        $this->producto('Yogur', 'Marca Vieja');

        $servicio = app(SincronizarCatalogo::class);
        $servicio->aplicar($servicio->analizar($this->lista([
            ['Barra proteica', 'FitMarket'],
            ['Yogur', 'Marca Nueva'],
        ])));

        $this->assertTrue($a->fresh()->activo, 'Se apagó un duplicado que no tenía nada que ver.');
        $this->assertTrue($b->fresh()->activo);
    }

    /** Pero sí unifica los que quedaron colisionando por el cambio de marca. */
    public function test_unifica_los_que_choca_el_cambio_de_marca(): void
    {
        // Ya existe "Queso" en "QU (Crudda)".
        $destino = $this->producto('Queso', 'QU (Crudda)');
        // Y otro "Queso" en "Crudda" que la lista manda a "QU (Crudda)".
        $movido = $this->producto('Queso', 'Crudda', ['imagen' => null]);

        $servicio = app(SincronizarCatalogo::class);
        $servicio->aplicar($servicio->analizar($this->lista([['Queso', 'QU (Crudda)']])));

        // Uno de los dos queda apagado (el movido, sin foto).
        $this->assertFalse($movido->fresh()->activo);
        $this->assertTrue($destino->fresh()->activo);
    }

    // --- #11: reimportar reactiva lo que volvió a la lista -----------------

    public function test_reimportar_reactiva_un_producto_dado_de_baja(): void
    {
        $producto = $this->producto('Barra que volvió', 'NotCo', ['activo' => false]);

        $columnMap = [
            'nombre' => 'Nombre', 'marca' => 'Marca', 'categoria' => 'Categoría',
            'unidad' => 'Unidad', 'precio' => 'Precio', 'stock' => '',
            'sin_tacc' => '', 'congelado' => '', 'nuevo' => '',
        ];

        $result = (new ProductImportService)->import(
            $this->lista([['Barra que volvió', 'NotCo']]), $columnMap, 5
        );

        $this->assertTrue($producto->fresh()->activo, 'El producto que volvió a la lista siguió apagado.');
        $this->assertSame(1, $result['productos_reactivados']);
    }

    public function test_un_producto_que_sigue_activo_no_cuenta_como_reactivado(): void
    {
        $this->producto('Sigue viva', 'NotCo');

        $columnMap = [
            'nombre' => 'Nombre', 'marca' => 'Marca', 'categoria' => 'Categoría',
            'unidad' => 'Unidad', 'precio' => 'Precio', 'stock' => '',
            'sin_tacc' => '', 'congelado' => '', 'nuevo' => '',
        ];

        $result = (new ProductImportService)->import(
            $this->lista([['Sigue viva', 'NotCo']]), $columnMap, 5
        );

        $this->assertSame(0, $result['productos_reactivados']);
    }
}
