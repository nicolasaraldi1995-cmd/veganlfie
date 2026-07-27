<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Caja;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CajaTest extends TestCase
{
    use RefreshDatabase;

    public function test_operador_no_puede_acceder_a_caja(): void
    {
        $operador = User::factory()->create(['role' => 'operador']);

        $response = $this->actingAs($operador)->get(Caja::getUrl());

        $response->assertForbidden();
    }

    public function test_admin_puede_acceder_a_caja(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(Caja::getUrl());

        $response->assertOk();
    }

    public function test_la_caja_suma_solo_efectivo_y_transferencia_y_muestra_los_demas_medios_aparte(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cliente = User::factory()->create(['role' => 'cliente']);

        Pago::create(['user_id' => $cliente->id, 'monto' => 1000, 'metodo' => 'efectivo', 'fecha' => '2026-06-10']);
        Pago::create(['user_id' => $cliente->id, 'monto' => 2000, 'metodo' => 'transferencia', 'fecha' => '2026-06-15']);
        Pago::create(['user_id' => $cliente->id, 'monto' => 5000, 'metodo' => 'mercadopago', 'fecha' => '2026-06-12']);

        $resumen = Livewire::actingAs($admin)
            ->test(Caja::class)
            ->set('desde', '2026-06-06')
            ->set('hasta', '2026-06-21')
            ->call('generar')
            ->get('resumen');

        $this->assertEquals(3000.0, $resumen['totalCaja']);
        $this->assertEquals(5000.0, $resumen['porMetodo']['mercadopago']['total']);
        $this->assertFalse($resumen['porMetodo']['mercadopago']['incluido']);
        $this->assertTrue($resumen['porMetodo']['efectivo']['incluido']);
        $this->assertCount(3, $resumen['detalle']);
    }

    public function test_excluye_pagos_fuera_de_rango_y_los_de_pedidos_cancelados(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $cliente = User::factory()->create(['role' => 'cliente']);
        $pedidoCancelado = Pedido::factory()->create(['user_id' => $cliente->id, 'estado' => 'canceled']);

        // Fuera del rango elegido.
        Pago::create(['user_id' => $cliente->id, 'monto' => 9999, 'metodo' => 'efectivo', 'fecha' => '2026-05-30']);
        // Dentro del rango, pero el pedido al que estaba atado se canceló después: no debe contar.
        Pago::create(['user_id' => $cliente->id, 'pedido_id' => $pedidoCancelado->id, 'monto' => 8888, 'metodo' => 'efectivo', 'fecha' => '2026-06-10']);
        // Este sí cuenta.
        Pago::create(['user_id' => $cliente->id, 'monto' => 1500, 'metodo' => 'efectivo', 'fecha' => '2026-06-11']);

        $resumen = Livewire::actingAs($admin)
            ->test(Caja::class)
            ->set('desde', '2026-06-06')
            ->set('hasta', '2026-06-21')
            ->call('generar')
            ->get('resumen');

        $this->assertEquals(1500.0, $resumen['totalCaja']);
        $this->assertCount(1, $resumen['detalle']);
    }
}
