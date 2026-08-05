<?php

namespace Tests\Feature\Agent;

use App\Models\User;
use App\Services\Agent\ToolRegistry;
use App\Services\Agent\Tools\GetRecentSystemErrorsTool;
use App\Services\Agent\Tools\GetScheduledJobHealthTool;
use App\Services\Agent\Tools\GetSystemHealthTool;
use App\Services\Agent\Tools\ListUserAccountsTool;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The estate-level tools, and the routing that makes them reachable.
 *
 * These exist because of a real exchange: asked whether it could help with the
 * system, the assistant answered "I can't write code" and stopped — accurate,
 * but useless, because it had no way to see the system at all. It still cannot
 * write code; it can now look.
 */
class SuperAdminSystemToolsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A scratch log for the error-reading tests.
     *
     * Emphatically NOT the real log: these run against a live deployment, and
     * truncating storage/logs/laravel.log to fixture it would lose whatever
     * php-fpm wrote in the meantime — and it is multiple megabytes to restore.
     * The tool reads its path from config, so pointing config here is enough.
     */
    private function useScratchLog(string $contents): void
    {
        $path = storage_path('logs/testing-agent-errors.log');
        file_put_contents($path, $contents);
        config(['logging.channels.single.path' => $path]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('logs/testing-agent-errors.log'));
        parent::tearDown();
    }

    public function test_a_super_admin_chat_is_given_the_system_tool_set(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        // The chat endpoint streams, so assert the wiring at the seam it
        // depends on rather than trying to parse an SSE body.
        $this->assertArrayHasKey(
            'getSystemHealth',
            app(ToolRegistry::class)->forAudience(ToolRegistry::AUDIENCE_SUPER_ADMIN)
        );

        $this->assertTrue($superAdmin->isSuperAdmin());
    }

    public function test_the_super_admin_prompt_states_what_it_cannot_do(): void
    {
        $prompt = app(AIService::class)->buildSuperAdminSystemPrompt();

        // The specific failure being fixed: a flat refusal with no follow-up.
        $this->assertStringContainsString('CANNOT write code', $prompt);
        $this->assertStringContainsString('do not just refuse and stop', $prompt);
        // And it must still carry everything the admin prompt does.
        $this->assertStringContainsString('CURRENT SHOP STATUS', $prompt);
    }

    public function test_system_health_reports_concerns_rather_than_raw_numbers_alone(): void
    {
        Cache::put('system_health', [
            'cpuLoad' => 20, 'memoryUsage' => 95, 'diskUsage' => 91, 'cpuTemp' => 45,
        ], 60);

        $result = app(GetSystemHealthTool::class)->execute([], null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Attention needed', $result->message);
        $this->assertStringContainsString('91% full', $result->message);
        $this->assertStringContainsString('95% used', $result->message);
    }

    public function test_system_health_says_so_plainly_when_nothing_is_wrong(): void
    {
        Cache::put('system_health', [
            'cpuLoad' => 10, 'memoryUsage' => 40, 'diskUsage' => 20, 'cpuTemp' => 45,
        ], 60);

        $result = app(GetSystemHealthTool::class)->execute([], null);

        $this->assertStringContainsString('normal', $result->message);
        $this->assertSame([], $result->data['concerns']);
    }

    /**
     * Health must come from each command's heartbeat, not its output — the same
     * trap that made the dashboard report agent:analyze dead after five
     * perfectly ordinary, signal-free days.
     */
    public function test_scheduled_job_health_reads_heartbeats(): void
    {
        Cache::forget('agent_analyze_last_run');
        Cache::put('enforce_sessions_last_run', now()->timestamp, 3600);
        Cache::put('ai_learn_last_run', now()->timestamp, 3600);
        Cache::put('barista_forecast_deep', ['x' => 1], 3600);

        $result = app(GetScheduledJobHealthTool::class)->execute([], null);

        $this->assertStringContainsString('stalled', $result->message);
        $this->assertStringContainsString('agent:analyze', $result->message);

        Cache::put('agent_analyze_last_run', now()->timestamp, 3600);
        $healthy = app(GetScheduledJobHealthTool::class)->execute([], null);
        $this->assertStringContainsString('running normally', $healthy->message);
    }

    public function test_account_listing_exposes_roles_but_never_contact_details(): void
    {
        User::factory()->create(['role' => 'admin', 'name' => 'Ada Admin', 'email' => 'ada@example.test']);
        User::factory()->create(['role' => 'staff', 'name' => 'Sam Staff', 'email' => 'sam@example.test']);

        $result = app(ListUserAccountsTool::class)->execute([], null);

        $encoded = json_encode($result->data);
        $this->assertStringContainsString('Ada Admin', $encoded);
        $this->assertStringContainsString('Admin', $encoded);
        // A roster with contact details in a chat transcript is a
        // data-protection problem; the Accounts page shows the full record.
        $this->assertStringNotContainsString('ada@example.test', $encoded);
    }

    public function test_account_listing_can_narrow_to_one_role(): void
    {
        User::factory()->create(['role' => 'admin', 'name' => 'Ada Admin']);
        User::factory()->create(['role' => 'staff', 'name' => 'Sam Staff']);

        $result = app(ListUserAccountsTool::class)->execute(['role' => 'staff'], null);

        $encoded = json_encode($result->data);
        $this->assertStringContainsString('Sam Staff', $encoded);
        $this->assertStringNotContainsString('Ada Admin', $encoded);
    }

    /**
     * A log carries file paths, SQL and occasionally values from a failed
     * request. Anything that looks like a credential must not survive the trip
     * into a prompt.
     */
    public function test_recent_errors_redacts_anything_resembling_a_secret(): void
    {
        $stamp = now()->format('Y-m-d H:i:s');
        $this->useScratchLog("[{$stamp}] production.ERROR: Connection failed password=hunter2 token=abcdefghijklmnopqrstuvwxyz012345\n");

        $result = app(GetRecentSystemErrorsTool::class)->execute(['hours' => 1], null);
        $encoded = json_encode($result->data);

        $this->assertStringNotContainsString('hunter2', $encoded);
        $this->assertStringNotContainsString('abcdefghijklmnopqrstuvwxyz012345', $encoded);
        $this->assertStringContainsString('[redacted]', $encoded);
    }

    public function test_recent_errors_groups_repeats_instead_of_listing_every_one(): void
    {
        $stamp = now()->format('Y-m-d H:i:s');
        $line = "[{$stamp}] production.ERROR: Something specific broke in the same way\n";
        $this->useScratchLog(str_repeat($line, 5));

        $result = app(GetRecentSystemErrorsTool::class)->execute(['hours' => 1], null);

        $this->assertCount(1, $result->data['errors']);
        $this->assertSame(5, $result->data['errors'][0]['occurrences']);
        $this->assertStringContainsString('5 error(s)', $result->message);
    }

    public function test_recent_errors_ignores_anything_older_than_the_window(): void
    {
        $old = now()->subDays(5)->format('Y-m-d H:i:s');
        $this->useScratchLog("[{$old}] production.ERROR: An ancient failure nobody cares about now\n");

        $result = app(GetRecentSystemErrorsTool::class)->execute(['hours' => 24], null);

        $this->assertSame([], $result->data['errors']);
        $this->assertStringContainsString('No errors recorded', $result->message);
    }
}
