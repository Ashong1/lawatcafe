<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Notifications\SystemAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Owner (Oct 30): turn on remote access from the app — a Cloudflare Tunnel
 * to this server — see the link, turn it off. Off by default; no time limit.
 */
class RemoteAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-30 10:00:00');
        Process::fake(['*is-active*' => Process::result('inactive', exitCode: 3), '*' => Process::result()]);
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_the_security_page_shows_remote_access_off_by_default(): void
    {
        $this->actingAs($this->owner())->get(route('security'))->assertOk()
            ->assertSee('Remote access')->assertSee('Off')->assertSee('lawatkape-remote.aserotech.com')
            ->assertSee('Turn on')->assertDontSee('lawatkape-test');
    }

    public function test_the_owner_turns_it_on(): void
    {
        Notification::fake();
        $owner = $this->owner();
        $other = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($owner)->post(route('remote-access.on'))->assertRedirect()->assertSessionHas('success');

        Process::assertRan(fn ($p) => $p->command === ['sudo', '-n', '/usr/bin/systemctl', 'enable', '--now', 'lawatkape-remote']);
        $this->assertSame(1, ActivityLog::where('event', 'remote_access_on')->count());
        Notification::assertSentTo($other, SystemAlert::class);
    }

    public function test_turn_off_now(): void
    {
        $this->actingAs($this->owner())->post(route('remote-access.off'))->assertSessionHas('success');

        Process::assertRan(fn ($p) => $p->command === ['sudo', '-n', '/usr/bin/systemctl', 'disable', '--now', 'lawatkape-remote']);
        $this->assertSame(1, ActivityLog::where('event', 'remote_access_off')->count());
    }

    public function test_staff_cannot_turn_it_on(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))->post(route('remote-access.on'))
            ->assertRedirect(route('staff.dashboard'));
        Process::assertDidntRun(fn ($p) => in_array('enable', (array) $p->command, true));
    }

    /** Owner (Oct 30): from outside the shop only owner/admin accounts get in. */
    public function test_staff_cannot_sign_in_through_the_remote_link(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'username' => 'ben', 'password' => 'kape-2026']);

        $this->post('https://lawatkape-remote.aserotech.com/login', ['email' => 'ben', 'password' => 'kape-2026'])
            ->assertSessionHasErrors(['email' => 'From outside the shop only the owner and admins can sign in. Staff can sign in at the shop.']);
        $this->assertGuest();

        $this->post('https://lawatkape.aserotech.com/login', ['email' => 'ben', 'password' => 'kape-2026']);
        $this->assertAuthenticatedAs($staff);
    }

    public function test_a_staff_session_on_the_remote_link_is_signed_out(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get('https://lawatkape-remote.aserotech.com/staff-dashboard')
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($this->owner())->get('https://lawatkape-remote.aserotech.com/dashboard')->assertOk();
    }
}
