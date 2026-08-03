<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La lista de precios trae la planilla para tomar pedidos informales: es una
 * herramienta interna, no algo que el cliente deba ver.
 */
class ListaPreciosAccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_invitado_va_al_login(): void
    {
        $this->get('/lista-de-precios')->assertRedirect('/login');
    }

    public function test_un_cliente_no_puede_entrar(): void
    {
        $cliente = User::factory()->create(['role' => 'cliente']);

        $this->actingAs($cliente)->get('/lista-de-precios')->assertForbidden();
        $this->actingAs($cliente)->get('/lista-de-precios/html')->assertForbidden();
        $this->actingAs($cliente)->get('/lista-de-precios/pdf')->assertForbidden();
    }

    public function test_admin_y_operador_si_pueden(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $operador = User::factory()->create(['role' => 'operador']);

        $this->actingAs($admin)->get('/lista-de-precios')->assertOk();
        $this->actingAs($operador)->get('/lista-de-precios')->assertOk();
    }
}
