<?php

namespace Tests\Feature;

use App\Models\Configuracion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfiguracionTest extends TestCase
{
    use RefreshDatabase;

    public function test_actual_esta_memoizado_y_no_repite_la_consulta(): void
    {
        $this->assertSame(Configuracion::actual(), Configuracion::actual());
    }

    public function test_la_memoizacion_no_queda_pegada_entre_tests(): void
    {
        // Laravel arma una app (y un contenedor) nueva por cada test, así que
        // este binding nunca debería llegar pre-cargado -- si en cambio se
        // memoizara en una propiedad estática cruda, un test anterior que ya
        // llamó a actual() dejaría ese valor pegado acá (PHPUnit corre todo
        // en un solo proceso PHP largo).
        $this->assertFalse(app()->bound(Configuracion::class.'@actual'));

        Configuracion::actual();

        $this->assertTrue(app()->bound(Configuracion::class.'@actual'));
    }
}
