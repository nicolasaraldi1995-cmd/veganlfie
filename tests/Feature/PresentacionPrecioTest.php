<?php

namespace Tests\Feature;

use App\Models\Presentacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresentacionPrecioTest extends TestCase
{
    use RefreshDatabase;

    public function test_precio_final_sin_oferta_es_el_precio_normal(): void
    {
        $presentacion = Presentacion::factory()->create(['precio' => 1000]);

        $this->assertEquals(1000.0, $presentacion->precio_final);
        $this->assertFalse($presentacion->estaEnOferta());
    }

    public function test_precio_final_con_porcentaje_de_oferta(): void
    {
        $presentacion = Presentacion::factory()->create([
            'precio' => 1000,
            'oferta_porcentaje' => 10,
        ]);

        $this->assertTrue($presentacion->estaEnOferta());
        $this->assertEquals(900.0, $presentacion->precio_final);
    }

    public function test_oferta_fuera_de_rango_de_fechas_no_aplica(): void
    {
        $presentacion = Presentacion::factory()->create([
            'precio' => 1000,
            'oferta_porcentaje' => 10,
            'oferta_fin' => now()->subDay(),
        ]);

        $this->assertFalse($presentacion->estaEnOferta());
        $this->assertEquals(1000.0, $presentacion->precio_final);
    }

    public function test_precio_oferta_fijo_tiene_prioridad_sobre_porcentaje(): void
    {
        $presentacion = Presentacion::factory()->create([
            'precio' => 1000,
            'oferta_porcentaje' => 10,
            'oferta_precio' => 750,
        ]);

        $this->assertEquals(750.0, $presentacion->precio_final);
    }
}
