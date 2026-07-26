<?php

namespace Tests\Feature;

use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use App\Services\CuentaClienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CuentaClienteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_pago_de_un_pedido_cancelado_no_descuenta_el_saldo_de_otro_pedido(): void
    {
        $cliente = User::factory()->create(['role' => 'cliente']);

        $cancelado = Pedido::factory()->create(['user_id' => $cliente->id, 'total' => 1000]);
        $cancelado->pagos()->create(['monto' => 1000, 'metodo' => 'efectivo', 'fecha' => now()]);
        $cancelado->update(['estado' => 'canceled']);

        Pedido::factory()->create(['user_id' => $cliente->id, 'estado' => 'pending', 'total' => 5000]);

        $svc = app(CuentaClienteService::class);

        $this->assertEquals(5000, $svc->saldoDe($cliente));

        $resumen = $svc->resumenPorCliente()->firstWhere('id', $cliente->id);
        $this->assertEquals(5000, $resumen['total']);
        $this->assertEquals(0, $resumen['pagado']);
        $this->assertEquals(5000, $resumen['saldo']);
    }

    public function test_un_pago_general_sigue_contando_aunque_otro_pedido_se_cancele(): void
    {
        $cliente = User::factory()->create(['role' => 'cliente']);

        $cancelado = Pedido::factory()->create(['user_id' => $cliente->id, 'total' => 1000]);
        $cancelado->update(['estado' => 'canceled']);

        Pedido::factory()->create(['user_id' => $cliente->id, 'estado' => 'pending', 'total' => 5000]);
        Pago::create(['user_id' => $cliente->id, 'monto' => 2000, 'metodo' => 'efectivo', 'fecha' => now()]);

        $svc = app(CuentaClienteService::class);

        $this->assertEquals(3000, $svc->saldoDe($cliente));
    }
}
