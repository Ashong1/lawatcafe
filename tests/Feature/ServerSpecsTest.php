<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ServerSpecs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owner (Oct 30): show the server's real specs on the Application Host card. */
class ServerSpecsTest extends TestCase
{
    use RefreshDatabase;

    public function test_specs_are_read_from_the_machine(): void
    {
        $s = app(ServerSpecs::class)->read();

        foreach (['processor', 'cores', 'memory', 'disk', 'system', 'runs_on', 'uptime', 'software'] as $key) {
            $this->assertArrayHasKey($key, $s);
        }
        $this->assertGreaterThan(0, $s['cores']);
        $this->assertStringContainsString('GB', $s['memory']);
        $this->assertStringContainsString('PHP '.PHP_MAJOR_VERSION, $s['software']);
    }

    public function test_the_system_dashboard_shows_them(): void
    {
        // Owner (Oct 30): the amounts at the gauges, e.g. "1.5 of 5.0 GB".
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))->get(route('dashboard'))
            ->assertOk()->assertSee('memoryUsedGb', false)->assertSeeInOrder(['Mem', ' of ', 'GB'])->assertSee('cores')->assertSee('Proxmox');
    }

    public function test_the_live_feed_carries_memory_in_gb(): void
    {
        $this->mock(\App\Services\OpnSenseService::class, fn ($m) => $m->shouldReceive('getInterfaceStats', 'listSessions', 'getArpTable', 'getDhcpLeases')->andReturn([]));
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))->getJson(route('admin.live-stats'))
            ->assertOk()->assertJsonStructure(['memoryUsedGb', 'memoryTotalGb']);
    }
}
