<?php

namespace Tests\Feature;

use App\Models\Combo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los precios se muestran solo a clientes con cuenta. Los controladores ponen
 * en null los precios calculados del combo, pero precio_manual es una columna
 * de verdad y viajaba igual en el payload de Inertia.
 */
class PreciosCombosOcultosTest extends TestCase
{
    use RefreshDatabase;

    private function comboConPrecioFijo(): Combo
    {
        return Combo::create([
            'nombre' => 'Combo de prueba',
            'precio_manual' => 12345.67,
            'descuento_porcentaje' => 15,
            'activo' => true,
        ]);
    }

    public function test_un_visitante_sin_cuenta_no_recibe_el_precio_del_combo(): void
    {
        $this->comboConPrecioFijo();

        foreach (['/', '/combos'] as $ruta) {
            $contenido = $this->get($ruta)->assertOk()->getContent();

            $this->assertStringNotContainsString('12345.67', $contenido, "El precio del combo viajó en {$ruta}.");
            $this->assertStringNotContainsString('precio_manual', $contenido, "precio_manual viajó en {$ruta}.");
        }
    }

    public function test_un_cliente_con_cuenta_si_ve_el_precio(): void
    {
        $this->comboConPrecioFijo();

        $contenido = $this->actingAs(User::factory()->create(['role' => 'cliente']))
            ->get('/combos')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('12345.67', $contenido);
    }
}
