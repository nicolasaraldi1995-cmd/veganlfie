<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Quién llega a cada pantalla. Leer las guardas una por una en el código se
 * presta a que una quede sin mirar; acá se pide cada dirección con cada rol y
 * se compara contra lo que tendría que pasar.
 *
 * DUEÑO   = admin: la plata, los costos y los datos de los clientes.
 * EMPLEADO = operador: carga pedidos y mantiene el catálogo, nada más.
 */
class MatrizDeAccesoTest extends TestCase
{
    use RefreshDatabase;

    /** Del dueño y de nadie más. */
    private const SOLO_DUENO = [
        'admin/caja',
        'admin/gastos',
        'admin/users',
        'admin/configuracion',
        'admin/actualizar-precios',
        'admin/ofertas-masivas',
        'admin/resumen-cuenta',
        // Subir un archivo acá reescribe el precio de todo el catálogo de una:
        // es manejo de precios, no carga de pedidos.
        'admin/importador',
    ];

    /** Del dueño y del empleado: el trabajo de todos los días. */
    private const DUENO_Y_EMPLEADO = [
        'admin',
        'admin/pedidos',
        'admin/productos',
        'admin/presentacions',
        'admin/marcas',
        'admin/categorias',
        'admin/combos',
        'admin/banners',
        'admin/cargar-pedido',
        'admin/cargar-pedido-desde-archivo',
        'lista-de-precios',
        'lista-de-precios/pdf',
        'lista-de-precios/html',
        'lista-de-precios/planilla',
    ];

    /**
     * @return array<string, array{0: string}>
     */
    public static function pantallasDelDueno(): array
    {
        return array_combine(self::SOLO_DUENO, array_map(fn ($r) => [$r], self::SOLO_DUENO));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function pantallasDelEquipo(): array
    {
        return array_combine(self::DUENO_Y_EMPLEADO, array_map(fn ($r) => [$r], self::DUENO_Y_EMPLEADO));
    }

    private function como(string $rol): self
    {
        $this->actingAs(User::factory()->create(['role' => $rol]));

        return $this;
    }

    #[DataProvider('pantallasDelDueno')]
    public function test_las_pantallas_del_dueno_las_abre_solo_el_dueno(string $ruta): void
    {
        $this->como('admin')->get($ruta)->assertOk();

        $this->como('operador')->get($ruta)->assertForbidden();
        $this->como('cliente')->get($ruta)->assertForbidden();
    }

    #[DataProvider('pantallasDelEquipo')]
    public function test_las_pantallas_del_equipo_las_abren_dueno_y_empleado(string $ruta): void
    {
        $this->como('admin')->get($ruta)->assertOk();
        $this->como('operador')->get($ruta)->assertOk();

        $this->como('cliente')->get($ruta)->assertForbidden();
    }

    /**
     * Sin cuenta no se ve ninguna: las del panel mandan al login y las del
     * sitio, al login del sitio. Lo que no puede pasar es un 200.
     */
    #[DataProvider('pantallasDelDueno')]
    public function test_sin_cuenta_no_se_abre_ninguna_pantalla_del_dueno(string $ruta): void
    {
        $this->get($ruta)->assertStatus(302);
    }

    #[DataProvider('pantallasDelEquipo')]
    public function test_sin_cuenta_no_se_abre_ninguna_pantalla_del_equipo(string $ruta): void
    {
        $this->get($ruta)->assertStatus(302);
    }

    /**
     * Las páginas de la tienda sí son de todos, con o sin cuenta. Va acá para
     * que si alguien cierra de más también se note.
     */
    public function test_la_tienda_sigue_abierta_para_cualquiera(): void
    {
        foreach (['/', '/productos', '/combos', '/ofertas', '/nuevos', '/veganlife', '/carrito', '/sitemap.xml'] as $ruta) {
            $this->get($ruta)->assertOk();
        }
    }
}
