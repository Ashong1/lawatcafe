<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_reach_admin_gated_routes(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)->get('/dashboard')->assertOk();
    }

    public function test_super_admin_can_reach_staff_gated_routes(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)->get('/staff-dashboard')->assertOk();
    }

    public function test_admin_cannot_reach_super_admin_only_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.settings.integrations'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_super_admin_can_reach_super_admin_only_settings(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)->get(route('admin.settings.integrations'))->assertOk();
    }

    public function test_staff_still_blocked_from_admin_routes(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->get('/dashboard');

        $response->assertRedirect(route('staff.dashboard'));
    }

    public function test_admin_still_reaches_staff_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/staff-dashboard')->assertOk();
    }
}
