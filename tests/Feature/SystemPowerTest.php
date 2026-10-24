<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SystemPowerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Mockery;
use Tests\TestCase;

/** Nothing here may really restart, power off or take the (shared) app down. */
class SystemPowerTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_the_owner_sees_the_page_staff_do_not(): void
    {
        Process::fake(['*' => Process::result(output: '')]);

        $this->actingAs($this->owner())->get(route('system-power.index'))->assertOk()
            ->assertSee('Restart web app')->assertSee('Shut down server')->assertSee('One-time setup')->assertSee('aria-label="Restart or shut down"', false);
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('system-power.index'))->assertRedirect();
    }

    public function test_a_wrong_password_does_nothing(): void
    {
        $power = $this->mock(SystemPowerService::class);
        $power->shouldNotReceive('restartApp', 'shutDownApp', 'restartServer', 'shutDownServer');

        $this->actingAs($this->owner())->post(route('system-power.act', 'restart-app'), ['password' => 'nope'])
            ->assertSessionHasErrors('password');
    }

    public function test_shutting_the_server_down_needs_the_typed_word(): void
    {
        $power = $this->mock(SystemPowerService::class);
        $power->shouldReceive('serverControlReady')->andReturn(true);
        $power->shouldNotReceive('shutDownServer');

        $this->actingAs($this->owner())->post(route('system-power.act', 'shutdown-server'), ['password' => 'password', 'confirm' => 'shut down'])
            ->assertSessionHasErrors('confirm');
    }

    public function test_server_actions_wait_for_the_one_time_setup(): void
    {
        Process::fake(['*' => Process::result(output: 'User www-data may run: (ALL) ALL')]);

        $this->actingAs($this->owner())->post(route('system-power.act', 'restart-server'), ['password' => 'password'])
            ->assertSessionHas('error');
        Process::assertDidntRun(fn ($p) => str_contains(is_array($p->command) ? implode(' ', $p->command) : $p->command, 'reboot'));
    }

    public function test_restarting_the_server_is_logged_then_runs_only_the_allowed_command(): void
    {
        Process::fake(['*' => Process::result(output: '(root) NOPASSWD: /usr/bin/systemctl reload php8.2-fpm, /usr/bin/systemctl reboot, /usr/bin/systemctl poweroff')]);
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('system-power.act', 'restart-server'), ['password' => 'password'])->assertSessionHas('success');

        Process::assertRan(fn ($p) => is_string($p->command) && str_contains($p->command, 'sudo -n /usr/bin/systemctl reboot'));
        $this->assertDatabaseHas('activity_logs', ['event' => 'server_restarting', 'user_id' => $owner->id]);
    }

    public function test_shutting_down_the_web_app_keeps_the_owner_in(): void
    {
        $this->mock(SystemPowerService::class)->shouldReceive('shutDownApp')->once()->andReturn('bypass-secret');

        $this->actingAs($this->owner())->post(route('system-power.act', 'shutdown-app'), ['password' => 'password'])
            ->assertRedirect('/bypass-secret');
    }

    public function test_the_back_soon_page_loads_nothing_from_outside(): void
    {
        $html = view('errors.503')->render();

        $this->assertStringContainsString('back soon', $html);
        $this->assertDoesNotMatchRegularExpression('#(src|href)="https?://#', $html);
    }
}
