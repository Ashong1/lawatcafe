<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PiholeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteBlockingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_site_blocking_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(PiholeService::class, function ($mock) {
            $mock->shouldReceive('blockedDomains')->once()->andReturn([
                ['domain' => 'facebook.com', 'comment' => null, 'enabled' => true],
                ['domain' => 'shady-vpn.example', 'comment' => 'reported by staff', 'enabled' => true],
            ]);
        });

        $response = $this->actingAs($admin)->get(route('network.site-blocking'))->assertOk();

        $response->assertViewHas('presets', function ($presets) {
            $facebook = $presets['Social Media']->firstWhere('domain', 'facebook.com');

            return $facebook['blocked'] === true;
        });

        $response->assertViewHas('customDomains', fn ($custom) => $custom->pluck('domain')->contains('shady-vpn.example')
            && ! $custom->pluck('domain')->contains('facebook.com'));
    }

    public function test_admin_can_toggle_a_preset_site_on(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(PiholeService::class, function ($mock) {
            $mock->shouldReceive('blockDomain')->once()->with('facebook.com')->andReturn(true);
        });

        $this->actingAs($admin)->post(route('network.site-blocking.toggle'), [
            'domain' => 'facebook.com',
            'block' => '1',
        ])->assertRedirect();
    }

    public function test_admin_can_toggle_a_site_off(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(PiholeService::class, function ($mock) {
            $mock->shouldReceive('unblockDomain')->once()->with('facebook.com')->andReturn(true);
        });

        $this->actingAs($admin)->post(route('network.site-blocking.toggle'), [
            'domain' => 'facebook.com',
            'block' => '0',
        ])->assertRedirect();
    }

    public function test_admin_can_add_a_custom_domain(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(PiholeService::class, function ($mock) {
            $mock->shouldReceive('blockDomain')->once()->with('shady-vpn.example', "Added via Lawa't Kape admin")->andReturn(true);
        });

        $this->actingAs($admin)->post(route('network.site-blocking.store'), [
            'domain' => 'shady-vpn.example',
        ])->assertRedirect();
    }

    public function test_adding_a_malformed_domain_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(PiholeService::class, function ($mock) {
            $mock->shouldNotReceive('blockDomain');
        });

        $this->actingAs($admin)->post(route('network.site-blocking.store'), [
            'domain' => 'not a domain',
        ])->assertSessionHasErrors('domain');
    }

    public function test_admin_can_remove_a_custom_domain(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->mock(PiholeService::class, function ($mock) {
            $mock->shouldReceive('removeDomain')->once()->with('shady-vpn.example')->andReturn(true);
        });

        $this->actingAs($admin)->delete(route('network.site-blocking.destroy', 'shady-vpn.example'))
            ->assertRedirect();
    }

    public function test_staff_cannot_reach_any_site_blocking_endpoint(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get(route('network.site-blocking'))->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->post(route('network.site-blocking.store'), ['domain' => 'facebook.com'])
            ->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->post(route('network.site-blocking.toggle'), ['domain' => 'facebook.com', 'block' => '1'])
            ->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->delete(route('network.site-blocking.destroy', 'facebook.com'))
            ->assertRedirect(route('staff.dashboard'));
    }
}
