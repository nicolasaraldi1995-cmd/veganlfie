<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/**
 * La lista de precios como Excel: es con lo que se le pasa el pedido al
 * proveedor (se filtra por su marca, se cargan las cantidades y se le manda la
 * captura), y también lo que se vuelve a subir al Importador con los precios
 * corregidos.
 */
class ListaPreciosExcelTest extends TestCase
{
    use RefreshDatabase;

    private function producto(string $marca, string $nombre, float $precio = 1000, int $stock = 5): Producto
    {
        $producto = Producto::factory()->create([
            'nombre' => $nombre,
            'marca_id' => Marca::firstOrCreate(['nombre' => $marca], ['activo' => true])->id,
            'categoria_id' => Categoria::factory()->create()->id,
        ]);

        Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'unidad' => '1u',
            'precio' => $precio,
            'stock' => $stock,
            'activo' => true,
        ]);

        return $producto;
    }

    private function hoja(): Worksheet
    {
        $respuesta = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/lista-de-precios/planilla');

        $respuesta->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $respuesta->headers->get('Content-Type'));

        $archivo = tempnam(sys_get_temp_dir(), 'lista_').'.xlsx';
        file_put_contents($archivo, $respuesta->streamedContent());

        // Sin calcular las fórmulas: se quieren leer tal cual quedaron escritas.
        $libro = IOFactory::createReader('Xlsx')->load($archivo);
        unlink($archivo);

        return $libro->getActiveSheet();
    }

    public function test_los_encabezados_son_los_que_reconoce_el_importador(): void
    {
        $this->producto('Felices las Vacas', 'Mozzarella');

        $hoja = $this->hoja();

        $this->assertSame(
            ['Marca', 'Nombre', 'Categoría', 'Unidad', 'Precio', 'Stock', 'Cant. a pedir', 'Total'],
            array_map(fn ($c) => $hoja->getCell($c.'1')->getValue(), ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']),
        );
    }

    /** Filtrar por el proveedor tiene que dejar sus productos juntos y en orden. */
    public function test_va_ordenada_por_marca(): void
    {
        $this->producto('Zeta', 'Tofu ahumado');
        $this->producto('Alfa', 'Queso crema');
        $this->producto('Alfa', 'Manteca');

        $hoja = $this->hoja();

        $this->assertSame(
            [['Alfa', 'Manteca'], ['Alfa', 'Queso crema'], ['Zeta', 'Tofu ahumado']],
            array_map(fn ($f) => [$hoja->getCell('A'.$f)->getValue(), $hoja->getCell('B'.$f)->getValue()], [2, 3, 4]),
        );
    }

    /** La columna del pedido va vacía y el total se calcula solo en Excel. */
    public function test_trae_la_columna_para_cargar_el_pedido_con_su_total(): void
    {
        $this->producto('Felices las Vacas', 'Mozzarella', precio: 1500);

        $hoja = $this->hoja();

        $this->assertNull($hoja->getCell('G2')->getValue());
        $this->assertSame('=IF(G2="","",G2*E2)', $hoja->getCell('H2')->getValue());
        $this->assertEquals(1500, $hoja->getCell('E2')->getValue());
    }

    /**
     * Un producto que se llame "=HYPERLINK(...)" no se puede ejecutar al abrir
     * el archivo en la máquina de quien lo baje.
     */
    public function test_un_nombre_que_parece_formula_queda_como_texto(): void
    {
        $this->producto('Felices las Vacas', '=1+1');

        $celda = $this->hoja()->getCell('B2');

        $this->assertSame('=1+1', $celda->getValue());
        $this->assertSame('s', $celda->getDataType(), 'Excel lo iba a tomar como fórmula.');
    }

    public function test_el_encabezado_queda_fijo_y_con_filtro(): void
    {
        $this->producto('Felices las Vacas', 'Mozzarella');

        $hoja = $this->hoja();

        $this->assertSame('A2', $hoja->getFreezePane());
        $this->assertSame('A1:H2', $hoja->getAutoFilter()->getRange());
    }
}
