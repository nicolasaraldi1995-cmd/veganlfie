<?php

namespace Tests\Feature;

use App\Models\Presentacion;
use App\Models\Producto;
use App\Services\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private array $columnMap = [
        'nombre' => 'nombre',
        'marca' => 'marca',
        'categoria' => 'categoria',
        'unidad' => 'unidad',
        'precio' => 'precio',
        'stock' => 'stock',
        'sin_tacc' => 'sin_tacc',
        'congelado' => 'congelado',
        'nuevo' => 'nuevo',
    ];

    private function csvPath(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'import_test_').'.csv';
        file_put_contents($path, $contents);

        return $path;
    }

    public function test_importa_un_precio_con_formato_argentino_de_miles_y_decimales(): void
    {
        $csv = "nombre,marca,categoria,unidad,precio,stock\n"
            ."Leche de Almendras,Notco,Lacteos,1L,\"1.234,56\",10\n";

        (new ProductImportService)->import($this->csvPath($csv), $this->columnMap);

        $this->assertEquals(1234.56, (float) Presentacion::first()->precio);
    }

    public function test_importa_un_precio_numerico_simple_de_excel(): void
    {
        $csv = "nombre,marca,categoria,unidad,precio,stock\n"
            ."Leche de Almendras,Notco,Lacteos,1L,1234.56,10\n";

        (new ProductImportService)->import($this->csvPath($csv), $this->columnMap);

        $this->assertEquals(1234.56, (float) Presentacion::first()->precio);
    }

    public function test_un_precio_negativo_se_rechaza_y_no_crea_el_producto(): void
    {
        $csv = "nombre,marca,categoria,unidad,precio,stock\n"
            ."Tofu,Vegatos,Proteinas,500gr,-500,10\n";

        $result = (new ProductImportService)->import($this->csvPath($csv), $this->columnMap);

        $this->assertEquals(0, Presentacion::count());
        $this->assertEquals(0, Producto::count());
        $this->assertEquals(1, $result['filas_saltadas']);
        $this->assertNotEmpty($result['errores']);
    }

    public function test_un_stock_negativo_se_guarda_como_cero(): void
    {
        $csv = "nombre,marca,categoria,unidad,precio,stock\n"
            ."Seitan,Vegatos,Proteinas,300gr,1000,-5\n";

        (new ProductImportService)->import($this->csvPath($csv), $this->columnMap);

        $this->assertSame(0, Presentacion::first()->stock);
    }

    public function test_reimportar_el_mismo_producto_actualiza_en_vez_de_duplicar(): void
    {
        $service = new ProductImportService;

        $csv1 = "nombre,marca,categoria,unidad,precio,stock\n"
            ."Hamburguesa Vegana,Vegatos,Congelados,x4,2000,10\n";
        $service->import($this->csvPath($csv1), $this->columnMap);

        $csv2 = "nombre,marca,categoria,unidad,precio,stock\n"
            ."Hamburguesa Vegana,Vegatos,Congelados,x4,2500,10\n";
        $result = $service->import($this->csvPath($csv2), $this->columnMap);

        $this->assertEquals(1, Producto::count());
        $this->assertEquals(1, Presentacion::count());
        $this->assertEquals(1, $result['productos_actualizados']);
        $this->assertEquals(2500.0, (float) Presentacion::first()->precio);
    }

    public function test_una_fila_sin_marca_se_saltea_sin_crear_nada(): void
    {
        $csv = "nombre,marca,categoria,unidad,precio,stock\n"
            ."Producto Sin Marca,,Congelados,x4,2000,10\n";

        $result = (new ProductImportService)->import($this->csvPath($csv), $this->columnMap);

        $this->assertEquals(0, Producto::count());
        $this->assertEquals(1, $result['filas_saltadas']);
    }

    /**
     * El bug que partía el catálogo por mil: un precio sin centavos, que es como
     * viene la mayoría de las listas de almacén.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('preciosSinCentavos')]
    public function test_un_precio_de_miles_sin_centavos_no_se_parte_por_mil(string $celda, float $esperado): void
    {
        $csv = "nombre,marca,categoria,unidad,precio,stock\n"
            ."Aceite,Natura,Aceites,900ml,\"{$celda}\",10\n";

        (new ProductImportService)->import($this->csvPath($csv), $this->columnMap);

        $this->assertEquals($esperado, (float) Presentacion::first()->precio);
    }

    public static function preciosSinCentavos(): array
    {
        return [
            'mil ciento cinco' => ['1.105', 1105.0],
            'doce mil quinientos' => ['12.500', 12500.0],
            'quince mil novecientos' => ['15.900', 15900.0],
            'con símbolo adelante' => ['$ 1.105', 1105.0],
            'millón' => ['1.234.567', 1234567.0],
            'con centavos igual anda' => ['1.234,56', 1234.56],
        ];
    }

    /** Una celda vacía no puede pisar el precio que ya tenía el producto. */
    public function test_una_celda_de_precio_vacia_no_pisa_el_precio_existente(): void
    {
        $service = new ProductImportService;
        $service->import($this->csvPath("nombre,marca,categoria,unidad,precio,stock\nMani,Natura,Snacks,200gr,5000,10\n"), $this->columnMap);

        $result = $service->import($this->csvPath("nombre,marca,categoria,unidad,precio,stock\nMani,Natura,Snacks,200gr,,10\n"), $this->columnMap);

        $this->assertEquals(5000.0, (float) Presentacion::first()->precio, 'La celda vacía pisó el precio con 0.');
        $this->assertSame(1, $result['presentaciones_sin_precio']);
        $this->assertSame(0, $result['presentaciones_actualizadas']);
    }

    /** Texto en la celda de precio ("Consultar") tampoco lo pisa. */
    public function test_un_precio_no_numerico_no_pisa_el_precio_existente(): void
    {
        $service = new ProductImportService;
        $service->import($this->csvPath("nombre,marca,categoria,unidad,precio,stock\nMani,Natura,Snacks,200gr,5000,10\n"), $this->columnMap);

        $service->import($this->csvPath("nombre,marca,categoria,unidad,precio,stock\nMani,Natura,Snacks,200gr,Consultar,10\n"), $this->columnMap);

        $this->assertEquals(5000.0, (float) Presentacion::first()->precio);
    }

    /** Un producto nuevo sin precio no se crea: nacería a $0 y comprable. */
    public function test_un_producto_nuevo_sin_precio_no_se_crea(): void
    {
        $csv = "nombre,marca,categoria,unidad,precio,stock\n"
            ."Nuevo,Natura,Snacks,100gr,,10\n";

        $result = (new ProductImportService)->import($this->csvPath($csv), $this->columnMap);

        $this->assertEquals(0, Presentacion::count());
        $this->assertSame(1, $result['filas_saltadas']);
    }

    /** El signo negativo escondido tras un símbolo de moneda igual se rechaza. */
    public function test_un_precio_negativo_con_simbolo_adelante_se_rechaza(): void
    {
        $csv = "nombre,marca,categoria,unidad,precio,stock\n"
            ."Tofu,Vegatos,Proteinas,500gr,\"$-100\",10\n";

        $result = (new ProductImportService)->import($this->csvPath($csv), $this->columnMap);

        $this->assertEquals(0, Presentacion::count());
        $this->assertNotEmpty($result['errores']);
    }
}
