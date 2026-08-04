<?php

namespace Tests\Feature\Admin;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Services\SincronizarCatalogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SincronizarCatalogoTest extends TestCase
{
    use RefreshDatabase;

    private function producto(string $nombre, string $marca): Producto
    {
        return Producto::create([
            'nombre' => $nombre,
            'marca_id' => Marca::firstOrCreate(['nombre' => $marca])->id,
            'categoria_id' => Categoria::firstOrCreate(['nombre' => 'Prueba'])->id,
            'activo' => true,
        ]);
    }

    private function lista(array $filas): string
    {
        $html = '<table>'.str_repeat('<tr><td>x</td></tr>', 4)
            .'<tr><td>Nombre</td><td>Marca</td><td>Categoría</td><td>Unidad</td><td>Precio</td></tr>';

        foreach ($filas as [$nombre, $marca]) {
            $html .= "<tr><td>{$nombre}</td><td>{$marca}</td><td>Prueba</td><td>1u</td><td>1.000,00</td></tr>";
        }

        $ruta = tempnam(sys_get_temp_dir(), 'lista_').'.xls';
        file_put_contents($ruta, $html.'</table>');

        return $ruta;
    }

    public function test_detecta_y_aplica_el_cambio_de_marca(): void
    {
        $producto = $this->producto('Yogurt Coco Iogo de vainilla', 'Crudda');

        $servicio = app(SincronizarCatalogo::class);
        $plan = $servicio->analizar($this->lista([['Yogurt Coco Iogo de vainilla', 'QU (Coco Iogo)']]));

        $this->assertCount(1, $plan['cambiosDeMarca']);
        $this->assertSame('QU (Coco Iogo)', $plan['cambiosDeMarca'][0]['marcaNueva']);
        $this->assertCount(0, $plan['bajas']);

        $servicio->aplicar($plan);

        $this->assertSame('QU (Coco Iogo)', $producto->fresh()->marca->nombre);
        $this->assertTrue($producto->fresh()->activo, 'No tendría que darse de baja: es el mismo producto.');
    }

    public function test_detecta_y_aplica_el_cambio_de_nombre(): void
    {
        $producto = $this->producto('Shogurt de almendras sabor durazno', 'Felices las vacas');

        $servicio = app(SincronizarCatalogo::class);
        $plan = $servicio->analizar($this->lista([['Jogurtti de almendras sabor durazno', 'Felices las vacas']]));

        $this->assertCount(1, $plan['cambiosDeNombre']);

        $servicio->aplicar($plan);

        $this->assertSame('Jogurtti de almendras sabor durazno', $producto->fresh()->nombre);
    }

    /**
     * Renombrar no cambia el slug: los enlaces que ya circulan tienen que seguir
     * funcionando.
     */
    public function test_al_renombrar_no_cambia_la_direccion_web(): void
    {
        $producto = $this->producto('Shogurt de almendras sabor durazno', 'Felices las vacas');
        $slug = $producto->slug;

        $servicio = app(SincronizarCatalogo::class);
        $servicio->aplicar($servicio->analizar($this->lista([['Jogurtti de almendras sabor durazno', 'Felices las vacas']])));

        $this->assertSame($slug, $producto->fresh()->slug);
    }

    public function test_da_de_baja_lo_que_no_esta_en_la_lista(): void
    {
        $producto = $this->producto('Producto discontinuado', 'NotCo');

        $servicio = app(SincronizarCatalogo::class);
        $plan = $servicio->analizar($this->lista([['Otra cosa distinta', 'Otra marca']]));

        $this->assertCount(1, $plan['bajas']);

        $servicio->aplicar($plan);

        // Baja lógica: sigue existiendo, con su foto y su historial.
        $this->assertFalse($producto->fresh()->activo);
        $this->assertNotNull(Producto::withTrashed()->find($producto->id));
    }

    public function test_no_toca_lo_que_ya_coincide(): void
    {
        $producto = $this->producto('Queso cremoso', 'Biorganic');

        $servicio = app(SincronizarCatalogo::class);
        $plan = $servicio->analizar($this->lista([['Queso cremoso', 'Biorganic']]));

        $this->assertSame(1, $plan['sinCambios']);
        $this->assertCount(0, $plan['bajas']);
        $this->assertCount(0, $plan['cambiosDeMarca']);
        $this->assertCount(0, $plan['cambiosDeNombre']);
    }

    /**
     * Un nombre parecido pero de otra marca no se toma como renombrado: hay
     * demasiado margen para equivocarse y terminar pisando otro producto.
     */
    public function test_un_nombre_parecido_de_otra_marca_no_cuenta_como_renombrado(): void
    {
        $this->producto('Leche de almendras chocolatada', 'Felices las vacas');

        $plan = app(SincronizarCatalogo::class)
            ->analizar($this->lista([['Leche de almendras chocolatadaa', 'Vrink']]));

        $this->assertCount(0, $plan['cambiosDeNombre']);
        $this->assertCount(1, $plan['bajas']);
    }
}
