<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Presentacion;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cuántas unidades hay es dato del sistema. Afuera alcanza con saber si el
 * botón de comprar va habilitado o bloqueado: el número exacto le dibujaba el
 * inventario, producto por producto, a cualquiera que mirara el código de la
 * página.
 */
class StockNoSaleAlPublicoTest extends TestCase
{
    use RefreshDatabase;

    private const UNIDADES = 137;

    private function producto(): Producto
    {
        $producto = Producto::factory()->create([
            'nombre' => 'Producto con stock',
            'marca_id' => Marca::factory()->create()->id,
            'categoria_id' => Categoria::factory()->create()->id,
        ]);

        Presentacion::factory()->create([
            'producto_id' => $producto->id,
            'precio' => 5000,
            'stock' => self::UNIDADES,
            'activo' => true,
        ]);

        return $producto;
    }

    public function test_el_numero_de_unidades_no_viaja_a_ninguna_pagina_publica(): void
    {
        $producto = $this->producto();

        foreach ([null, 'cliente'] as $rol) {
            if ($rol) {
                $this->actingAs(User::factory()->create(['role' => $rol]));
            }

            foreach (['/', '/productos', "/productos/{$producto->slug}"] as $ruta) {
                $contenido = (string) $this->get($ruta)->assertOk()->getContent();
                $quien = $rol ?? 'visitante sin cuenta';

                $this->assertStringNotContainsString((string) self::UNIDADES, $contenido, "El número de unidades viajó a {$ruta} ({$quien}).");
                $this->assertStringNotContainsString('&quot;stock&quot;', $contenido, "La columna stock viajó a {$ruta} ({$quien}).");
            }
        }
    }

    public function test_pero_si_viaja_si_hay_o_no_hay(): void
    {
        $producto = $this->producto();

        $contenido = (string) $this->get("/productos/{$producto->slug}")->assertOk()->getContent();

        // Es lo que decide si el botón de comprar va habilitado.
        $this->assertStringContainsString('hay_stock&quot;:true', $contenido);
    }

    public function test_sin_unidades_el_boton_queda_bloqueado(): void
    {
        $producto = $this->producto();
        $producto->presentaciones()->update(['stock' => 0]);

        $contenido = (string) $this->get("/productos/{$producto->slug}")->assertOk()->getContent();

        $this->assertStringContainsString('hay_stock&quot;:false', $contenido);
    }

    /**
     * El aviso de "pediste de más" tampoco puede decir cuántas quedan: sería
     * la forma de sacar el inventario de a un producto por vez.
     */
    public function test_el_aviso_de_pediste_de_mas_no_dice_cuantas_quedan(): void
    {
        $producto = $this->producto();
        $presentacion = $producto->presentaciones()->first();

        $respuesta = $this->actingAs(User::factory()->create(['role' => 'cliente']))
            ->post('/carrito/add', ['presentacion_id' => $presentacion->id, 'cantidad' => self::UNIDADES + 10]);

        $respuesta->assertSessionHasErrors('cantidad');

        $aviso = (string) session('errors')->getBag('default')->first('cantidad');

        $this->assertNotSame('', $aviso, 'Tendría que avisar algo.');
        $this->assertStringNotContainsString((string) self::UNIDADES, $aviso, "El aviso dice cuántas quedan: «{$aviso}»");
    }

    /** El equipo sí necesita el número: la lista de precios lo muestra. */
    public function test_el_equipo_sigue_viendo_las_unidades(): void
    {
        $this->producto();

        $contenido = (string) $this->actingAs(User::factory()->create(['role' => 'operador']))
            ->get('/lista-de-precios')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString((string) self::UNIDADES, $contenido);
    }
}
