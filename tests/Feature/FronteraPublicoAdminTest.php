<?php

namespace Tests\Feature;

use App\Filament\Pages\ResumenCuenta;
use App\Models\Categoria;
use App\Models\Combo;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lo que es del mostrador de adentro no tiene que llegar al de afuera. Acá se
 * fija lo que un desconocido, o un cliente con cuenta, no debería poder ver ni
 * hacer.
 */
class FronteraPublicoAdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * /media sirve cualquier archivo del disco del panel. Ahí adentro también
     * están los que sube el Importador, que son internos: la lista de precios
     * con los costos. Sin filtro, se bajaban sin siquiera estar logueado.
     */
    public function test_no_sirve_los_archivos_que_subio_el_importador(): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');
        Storage::disk('public')->put('imports/lista-de-precios.xls', 'costos y margenes');

        $this->get('/media/imports/lista-de-precios.xls')->assertNotFound();
    }

    public function test_las_carpetas_de_imagenes_se_siguen_sirviendo(): void
    {
        config(['filament.default_filesystem_disk' => 'public']);
        Storage::fake('public');

        foreach (['productos', 'marcas', 'categorias', 'banners', 'combos'] as $carpeta) {
            Storage::disk('public')->put("{$carpeta}/foto.jpg", 'contenido');
            $this->get("/media/{$carpeta}/foto.jpg")->assertOk();
        }
    }

    /**
     * El resumen de cuenta lista la deuda y el celular de todos los clientes, y
     * desde que tiene el botón de cobrar también registra plata. Eso es del
     * dueño, igual que Caja, Gastos y Clientes.
     */
    public function test_el_operador_no_entra_al_resumen_de_cuenta(): void
    {
        $this->assertFalse(
            ResumenCuenta::canAccess(),
            'Sin nadie logueado no tendría que dejar entrar.'
        );

        $this->actingAs(User::factory()->create(['role' => 'operador']));
        $this->assertFalse(ResumenCuenta::canAccess());

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->assertTrue(ResumenCuenta::canAccess());
    }

    public function test_el_operador_no_puede_registrar_un_pago(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'operador']))
            ->test(ResumenCuenta::class)
            ->assertForbidden();
    }

    /**
     * El panel entero: un cliente no entra ni a la puerta.
     */
    public function test_el_cliente_no_entra_al_panel(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'cliente']))
            ->get('/admin')
            ->assertForbidden();
    }

    /**
     * Se llena el catálogo con valores marcados en cada columna interna, para
     * después buscarlos en lo que devuelve cada página.
     *
     * @return array<string, string> ruta pública => descripción
     */
    private function catalogoConValoresMarcados(): array
    {
        $marca = Marca::factory()->create([
            'nombre' => 'Marca Testigo',
            'margen_porcentaje' => 33.31,
            'descuento_porcentaje' => 22.21,
        ]);
        $categoria = Categoria::factory()->create(['nombre' => 'Categoria Testigo']);

        $producto = Producto::factory()->create([
            'nombre' => 'Producto Testigo',
            'marca_id' => $marca->id,
            'categoria_id' => $categoria->id,
            'nuevo' => true,
        ]);

        Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'precio' => 5000,
            'precio_costo' => 6789.01,
            'margen_porcentaje' => 41.51,
            'descuento_porcentaje' => 13.51,
            'oferta_porcentaje' => 10,
            'stock' => 10,
            'activo' => true,
        ]);

        Combo::create([
            'nombre' => 'Combo Testigo',
            'precio_manual' => 98765.43,
            'descuento_porcentaje' => 17.71,
            'activo' => true,
        ]);

        return [
            '/' => 'inicio',
            '/productos' => 'listado',
            "/productos/{$producto->slug}" => 'ficha del producto',
            "/marcas/{$marca->slug}" => 'página de la marca',
            "/categorias/{$categoria->slug}" => 'página de la categoría',
            '/combos' => 'combos',
            '/ofertas' => 'ofertas',
            '/nuevos' => 'nuevos',
            '/veganlife' => 'la marca propia',
            '/carrito' => 'carrito',
            '/api/buscar?q=Testigo' => 'buscador',
        ];
    }

    /**
     * Barrido de todas las páginas de cara al público: nada de lo que es del
     * negocio puede viajar al navegador. Van juntos el nombre de la columna y
     * el valor, porque un número suelto también alcanza para deducir el costo.
     *
     * @return array<string, array{0: ?string}>
     */
    public static function quienMira(): array
    {
        return ['un visitante sin cuenta' => [null], 'un cliente con cuenta' => ['cliente']];
    }

    #[DataProvider('quienMira')]
    public function test_ninguna_pagina_publica_filtra_costos_ni_margenes(?string $rol): void
    {
        $rutas = $this->catalogoConValoresMarcados();

        if ($rol !== null) {
            $this->actingAs(User::factory()->create(['role' => $rol]));
        }

        $prohibido = [
            'precio_costo' => 'el nombre de la columna del costo',
            'margen_porcentaje' => 'el nombre de la columna del margen',
            '6789.01' => 'el costo del producto',
            '41.51' => 'el margen del producto',
            '33.31' => 'el margen de la marca',
            // El plano del panel: ver PreciosCombosOcultosTest para el precio
            // del combo, que es aparte porque el cliente con cuenta sí lo ve.
            'admin/resumen-cuenta' => 'la dirección del resumen de cuenta',
            'admin/configuracion' => 'la dirección de la configuración',
            'filament.admin' => 'el mapa de rutas del panel',
        ];

        foreach ($rutas as $ruta => $queEs) {
            $contenido = $this->get($ruta)->assertOk()->getContent();

            foreach ($prohibido as $aguja => $queDato) {
                $this->assertStringNotContainsString(
                    (string) $aguja,
                    (string) $contenido,
                    "En {$queEs} ({$ruta}) viajó {$queDato}."
                );
            }
        }
    }
}
