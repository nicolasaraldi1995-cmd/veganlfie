<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_operador_cannot_access_user_management(): void
    {
        $operador = User::factory()->create(['role' => 'operador']);

        $response = $this->actingAs($operador)->get('/admin/users');

        $response->assertForbidden();
    }

    public function test_admin_can_access_user_management(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
    }
}
