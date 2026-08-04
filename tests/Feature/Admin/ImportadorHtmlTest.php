<?php

namespace Tests\Feature\Admin;

use App\Models\Marca;
use App\Models\Producto;
use App\Services\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La lista de precios que exporta el sistema viejo se llama .xls pero por
 * dentro es una tabla HTML. Leerla con la librería de Excel tardaba 26
 * segundos para 1900 filas, y como el archivo se lee tres veces (encabezados,
 * previsualización e importación) la importación moría por timeout: error 504.
 */
class ImportadorHtmlTest extends TestCase
{
    use RefreshDatabase;

    private function archivo(): string
    {
        // Encabezados en la fila 5, como el archivo real. Incluye a propósito
        // el doble espacio y la fila de totales con fórmulas.
        $html = <<<'HTML'
        <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
        <table>
        <tr><td>VEGANLIFE</td></tr>
        <tr><td></td></tr>
        <tr><td>San Nicolás 1255</td></tr>
        <tr><td>Pergamino</td></tr>
        <tr><td>Nombre</td><td>Marca</td><td>Categoría</td><td>Unidad</td><td>Precio</td></tr>
        <tr><td>ADN Natural</td></tr>
        <tr><td>Queso  cheddar</td><td>Casa Vegana</td><td>Quesos</td><td>200gr</td><td>1500</td></tr>
        <tr><td>Leche de almendras</td><td>Casa Vegana</td><td>Bebidas</td><td>1lt</td><td>2000</td></tr>
        <tr><td></td><td>=SUMA(G8:G10)</td><td>=SUMA(H8:H10)</td><td></td><td></td></tr>
        </table>
        HTML;

        $ruta = tempnam(sys_get_temp_dir(), 'lista_').'.xls';
        file_put_contents($ruta, $html);

        return $ruta;
    }

    /**
     * El caso que reventó en producción: la marca ya existe pero escrita con
     * otros acentos o espacios. MySQL las considera la misma (ignora acentos y
     * mayúsculas), así que el importador tiene que encontrarla igual. Si no, la
     * intenta crear y choca contra el índice único del slug:
     *   Duplicate entry 'crudda-barras-proteicas' for key 'marcas_slug_unique'
     */
    public function test_encuentra_la_marca_aunque_cambien_acentos_y_espacios(): void
    {
        $marca = Marca::create(['nombre' => 'Crudda - Barras proteicas']);

        $html = <<<'HTML'
        <table>
        <tr><td>x</td></tr><tr><td>x</td></tr><tr><td>x</td></tr><tr><td>x</td></tr>
        <tr><td>Nombre</td><td>Marca</td><td>Categoría</td><td>Unidad</td><td>Precio</td></tr>
        <tr><td>Crudda bar brownie</td><td>Crudda- Barras proteícas</td><td>Barras</td><td>50gr</td><td>1.500,00</td></tr>
        </table>
        HTML;

        $ruta = tempnam(sys_get_temp_dir(), 'lista_').'.xls';
        file_put_contents($ruta, $html);

        $stats = (new ProductImportService)->import($ruta, [
            'nombre' => 'Nombre', 'marca' => 'Marca', 'categoria' => 'Categoría',
            'unidad' => 'Unidad', 'precio' => 'Precio', 'stock' => '',
            'sin_tacc' => '', 'congelado' => '', 'nuevo' => '',
        ], 5);

        $this->assertSame([], $stats['errores']);
        $this->assertSame(0, $stats['marcas_creadas'], 'Tendría que haber reusado la marca que ya existía.');
        $this->assertSame(1, Marca::count());
        $this->assertSame($marca->id, Producto::where('nombre', 'Crudda bar brownie')->first()?->marca_id);
    }

    public function test_lee_los_encabezados_de_la_fila_indicada(): void
    {
        $encabezados = (new ProductImportService)->getHeaders($this->archivo(), 5);

        $this->assertSame(['Nombre', 'Marca', 'Categoría', 'Unidad', 'Precio'], $encabezados);
    }

    public function test_importa_y_junta_los_espacios_repetidos(): void
    {
        $mapa = [
            'nombre' => 'Nombre', 'marca' => 'Marca', 'categoria' => 'Categoría',
            'unidad' => 'Unidad', 'precio' => 'Precio', 'stock' => '',
            'sin_tacc' => '', 'congelado' => '', 'nuevo' => '',
        ];

        $stats = (new ProductImportService)->import($this->archivo(), $mapa, 5);

        $this->assertSame(2, $stats['productos_creados']);

        // Un nombre con dos espacios seguidos se ve igual que con uno solo: si
        // no se normaliza, la próxima importación crea un producto duplicado.
        $this->assertNotNull(Producto::where('nombre', 'Queso cheddar')->first());
        $this->assertNull(Producto::where('nombre', 'Queso  cheddar')->first());
    }

    /**
     * La última fila del archivo trae fórmulas sin calcular. Sin limpiarlas se
     * creaba una marca llamada "=SUMA(G8:G10)".
     */
    public function test_la_fila_de_totales_no_crea_una_marca(): void
    {
        $mapa = [
            'nombre' => 'Nombre', 'marca' => 'Marca', 'categoria' => 'Categoría',
            'unidad' => 'Unidad', 'precio' => 'Precio', 'stock' => '',
            'sin_tacc' => '', 'congelado' => '', 'nuevo' => '',
        ];

        (new ProductImportService)->import($this->archivo(), $mapa, 5);

        $this->assertSame(0, Marca::where('nombre', 'like', '=%')->count());
        $this->assertSame(1, Marca::count(), 'Sólo tendría que existir "Casa Vegana".');
    }
}
