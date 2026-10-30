<?php

namespace Tests\Feature;

use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owner (Oct 30): log who opened the system, and who came in through the remote link. */
class SiteVisitLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visit_is_recorded_once_and_becomes_the_users_after_sign_in(): void
    {
        $owner = User::factory()->create(['role' => 'admin', 'username' => 'owner', 'password' => 'kape-2026']);

        $this->get('/login')->assertOk();
        $this->get('/login');
        $this->assertSame(1, SiteVisit::count());
        $this->assertNull(SiteVisit::first()->user_id);

        $this->post('/login', ['email' => 'owner', 'password' => 'kape-2026']);
        $this->get(route('dashboard'))->assertOk();

        $this->assertSame(1, SiteVisit::count());
        $this->assertSame($owner->id, SiteVisit::first()->user_id);
        $this->assertFalse(SiteVisit::first()->remote);
        $this->assertSame('/login', SiteVisit::first()->path);
    }

    public function test_a_visit_through_the_remote_link_is_marked_remote(): void
    {
        $this->get('https://lawatkape-remote.aserotech.com/login', ['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Mobile'])->assertOk();

        $visit = SiteVisit::first();
        $this->assertTrue($visit->remote);
        $this->assertSame('Phone', $visit->deviceKind());
    }

    public function test_the_guest_portal_and_background_requests_are_not_logged(): void
    {
        $this->get(route('portal.privacy'));
        $this->getJson('/login');
        $this->assertSame(0, SiteVisit::count());
    }

    public function test_the_security_page_lists_visits_and_filters_remote(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);
        SiteVisit::create(['remote' => true, 'ip_address' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0)', 'path' => '/login', 'user_id' => $owner->id]);
        SiteVisit::create(['remote' => false, 'ip_address' => '192.168.2.98', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0)', 'path' => '/dashboard']);

        $page = $this->actingAs($owner)->get(route('security', ['where' => 'remote']))->assertOk()
            ->assertSee('Who opened the system')->assertSee('Remote link')->assertSee('1 visit through the remote link');
        $page->assertDontSee('203.0.113.9');   // the owner sees the kind of device, not its address
        $this->assertStringNotContainsString('/dashboard</td>', $page->getContent());

        $this->actingAs(User::factory()->create(['role' => 'super_admin']))->get(route('security'))->assertSee('203.0.113.9');
    }

    public function test_visits_older_than_90_days_are_pruned(): void
    {
        $old = SiteVisit::create(['path' => '/login']);
        $old->forceFill(['created_at' => now()->subDays(91)])->save();
        SiteVisit::create(['path' => '/login']);

        $this->artisan('model:prune', ['--model' => [SiteVisit::class]]);

        $this->assertSame(1, SiteVisit::count());
    }
}
