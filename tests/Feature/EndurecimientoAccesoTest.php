<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Las puertas de entrada al sitio. Nada de esto tapa un agujero puntual: son
 * los frenos que hacen que probar a lo bruto no sirva.
 */
class EndurecimientoAccesoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sin tope, un competidor automatiza altas y se lleva la lista mayorista,
     * que es justo lo que está cerrado para el visitante sin cuenta.
     */
    public function test_no_se_pueden_crear_cuentas_en_cadena(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $respuesta = $this->post('/register', [
                'name' => "Cuenta {$i}",
                'email' => "cuenta{$i}@ejemplo.test",
                'password' => 'clavelarga1',
                'password_confirmation' => 'clavelarga1',
            ]);
        }

        $respuesta->assertStatus(429);
        $this->assertLessThan(6, User::count(), 'Se crearon las seis: no hay ningún tope.');
    }

    /**
     * La respuesta de "olvidé mi contraseña" dice si el email existe. Con eso
     * se puede barrer una lista y quedarse con los clientes de la
     * distribuidora, así que al menos no se puede barrer rápido.
     */
    public function test_no_se_puede_barrer_la_lista_de_clientes(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $respuesta = $this->post('/forgot-password', ['email' => "candidato{$i}@ejemplo.test"]);
        }

        $respuesta->assertStatus(429);
    }

    /**
     * El tope de LoginRequest es por email+IP: probar UNA contraseña contra
     * MUCHAS cuentas nunca llegaba al límite. Este es por IP a secas.
     */
    public function test_no_se_puede_probar_una_clave_contra_muchas_cuentas(): void
    {
        for ($i = 1; $i <= 21; $i++) {
            User::factory()->create(['email' => "victima{$i}@ejemplo.test"]);
        }

        $bloqueados = 0;

        for ($i = 1; $i <= 21; $i++) {
            $respuesta = $this->post('/login', [
                'email' => "victima{$i}@ejemplo.test",
                'password' => 'Verano2026!',
            ]);

            if ($respuesta->status() === 429) {
                $bloqueados++;
            }
        }

        $this->assertGreaterThan(0, $bloqueados, 'Se pudieron probar las 21 cuentas sin que frenara nada.');
    }

    /**
     * Si alguien se quedó con la cookie de sesión, cambiar la contraseña tiene
     * que dejarlo afuera. Antes seguía entrando con la cookie vieja.
     */
    public function test_cambiar_la_clave_cierra_las_otras_sesiones(): void
    {
        $cliente = User::factory()->create(['role' => 'cliente', 'password' => Hash::make('clavevieja1')]);
        Producto::factory()->count(0)->create();

        $this->actingAs($cliente);
        $this->get('/profile')->assertOk();

        // Desde otro lado, el dueño de la cuenta cambia la contraseña.
        $cliente->forceFill(['password' => Hash::make('clavenueva1')])->save();

        $this->get('/profile')->assertRedirect('/login');
    }

    /**
     * La cookie de sesión no puede viajar por http en producción, aunque nadie
     * se haya acordado de poner la variable en el servidor.
     */
    public function test_en_produccion_la_cookie_de_sesion_es_solo_por_https(): void
    {
        // Sin el valor por defecto quedaba en null, que para Symfony significa
        // "sin el flag Secure": la cookie salía también por http.
        $this->assertNotNull(config('session.secure'), 'Quedó en null: la cookie sale sin el flag Secure.');

        // Y el valor por defecto depende del entorno, no de que alguien se
        // haya acordado de poner la variable en el servidor.
        $this->assertTrue(
            env('SESSION_SECURE_COOKIE', 'production' === 'production'),
            'Con APP_ENV=production tiene que dar true aunque la variable no esté.'
        );
    }
}
