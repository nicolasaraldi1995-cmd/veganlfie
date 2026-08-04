<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\ResumenCuenta;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cobrar era ir hasta Clientes y buscar de nuevo al cliente que ya se tenía
 * abierto. Ahora el pago se anota desde el mismo resumen donde se ve la deuda.
 */
class ResumenCuentaPagoTest extends TestCase
{
    use RefreshDatabase;

    private function clienteQueDebe(float $total): User
    {
        $cliente = User::factory()->create(['role' => 'cliente']);
        Pedido::factory()->create(['user_id' => $cliente->id, 'total' => $total]);

        return $cliente;
    }

    private function resumenAbierto(User $cliente): Testable
    {
        return Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(ResumenCuenta::class)
            ->set('cliente_id', (string) $cliente->id)
            ->call('verResumen');
    }

    public function test_registra_el_pago_y_baja_el_saldo_en_el_acto(): void
    {
        $cliente = $this->clienteQueDebe(10000);

        $componente = $this->resumenAbierto($cliente);
        $this->assertSame(10000.0, $componente->get('resumen')['saldoTotal']);

        $componente->callAction('registrarPago', [
            'monto' => 4000,
            'metodo' => 'transferencia',
            'fecha' => now()->toDateString(),
        ])->assertHasNoActionErrors();

        $pago = Pago::where('user_id', $cliente->id)->sole();
        $this->assertEquals(4000, $pago->monto);
        $this->assertSame('transferencia', $pago->metodo);
        // Sin pedido_id: es plata a cuenta, no el saldo de un pedido puntual.
        $this->assertNull($pago->pedido_id);

        // El resumen se recalcula solo, sin volver a apretar "Ver resumen".
        $this->assertSame(6000.0, $componente->get('resumen')['saldoTotal']);
    }

    public function test_el_cliente_que_salda_todo_sale_de_la_lista_de_los_que_deben(): void
    {
        $cliente = $this->clienteQueDebe(5000);

        $componente = $this->resumenAbierto($cliente);
        $this->assertCount(1, $componente->get('clientesConSaldo'));

        $componente->callAction('registrarPago', [
            'monto' => 5000,
            'metodo' => 'efectivo',
            'fecha' => now()->toDateString(),
        ])->assertHasNoActionErrors();

        $this->assertSame([], $componente->get('clientesConSaldo'));
    }

    public function test_el_monto_viene_cargado_con_lo_que_debe(): void
    {
        $componente = $this->resumenAbierto($this->clienteQueDebe(7500));

        $componente->mountAction('registrarPago')
            ->assertActionDataSet(['monto' => 7500.0, 'metodo' => 'efectivo']);
    }

    public function test_no_registra_un_pago_sin_monto(): void
    {
        $cliente = $this->clienteQueDebe(3000);

        $this->resumenAbierto($cliente)
            ->callAction('registrarPago', ['monto' => null, 'metodo' => 'efectivo', 'fecha' => now()->toDateString()])
            ->assertHasActionErrors(['monto']);

        $this->assertSame(0, Pago::where('user_id', $cliente->id)->count());
    }
}
