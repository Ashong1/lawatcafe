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
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))->get(route('dashboard'))
            ->assertOk()->assertSee('Processor')->assertSee('Memory')->assertSee('Runs on');
    }
}
