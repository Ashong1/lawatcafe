<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Agent\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Owner (Oct 30): "send me a download of the daily report" — the file, right in the chat. */
class ReportDownloadToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_hands_back_a_download_link_for_the_report_asked_for(): void
    {
        Carbon::setTestNow('2026-10-30 21:00:00');
        $tool = app(ToolRegistry::class)->forAudience(ToolRegistry::AUDIENCE_ADMIN)['getReportDownload'];

        $r = $tool->execute(['report' => 'sales', 'period' => 'month'], User::factory()->create(['role' => 'admin']))->toArray();

        $this->assertTrue($r['success']);
        $this->assertSame('/reports/sales.csv?period=month&date=2026-10-30', $r['data']['download']['url']);
        $this->assertStringContainsString('October 2026', $r['data']['download']['label']);

        $orders = $tool->execute(['report' => 'orders', 'period' => 'day', 'date' => '2026-10-29'], null)->toArray();
        $this->assertSame('/pos/history.csv?period=day&date=2026-10-29', $orders['data']['download']['url']);

        $this->assertFalse($tool->execute(['report' => 'payroll', 'period' => 'day'], null)->toArray()['success']);
    }

    public function test_it_is_for_the_owner_not_staff_and_needs_no_approval(): void
    {
        $registry = app(ToolRegistry::class);
        $this->assertArrayNotHasKey('getReportDownload', $registry->forAudience(ToolRegistry::AUDIENCE_STAFF));
        $this->assertSame('auto', $registry->forAudience(ToolRegistry::AUDIENCE_ADMIN)['getReportDownload']->permissionTier());
    }

    public function test_the_chat_shows_a_download_button(): void
    {
        $js = file_get_contents(resource_path('js/agent-chat.js'));
        $blade = file_get_contents(resource_path('views/components/agent-chat.blade.php'));

        $this->assertStringContainsString('download: e.result && e.result.data && e.result.data.download', $js);
        $this->assertSame(2, substr_count($blade, ':href="a.download.url"'));
    }
}
