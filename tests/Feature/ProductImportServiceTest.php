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
}
