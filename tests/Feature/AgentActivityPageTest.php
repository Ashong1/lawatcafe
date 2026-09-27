<?php

namespace Tests\Feature;

use App\Models\AiActionAudit;
use App\Models\User;
use App\Support\AgentActivityEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owner feedback: "make this more user friendly, the cafe owner may not
 * understand this". The page printed raw tool names, internal warning codes
 * and capitalised statuses.
 */
class AgentActivityPageTest extends TestCase
{
    use RefreshDatabase;

    private function audit(string $tool, string $status, array $result = [], array $params = [], ?int $actor = null): AiActionAudit
    {
        return AiActionAudit::create([
            'tool_name' => $tool, 'status' => $status, 'actor_type' => 'ai', 'actor_user_id' => $actor,
            'result' => $result, 'input_params' => $params,
        ]);
    }

    public function test_entries_read_as_plain_sentences(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Owner']);
        $this->audit('blockSites', 'executed', ['success' => true, 'message' => 'Blocked for all guests: beeg.com.'], [], $admin->id);
        $this->audit('getAnomalySignals', 'executed', ['success' => true, 'message' => '1 anomaly signal(s): voucher_revenue_divergence (1).',
            'data' => ['signals' => [['type' => 'voucher_revenue_divergence']]]]);

        $html = $this->actingAs($admin)->get(route('admin.ai.actions.index', ['show' => 'all']))->assertOk()->getContent();

        $this->assertStringContainsString('Blocked websites for guests', $html);
        $this->assertStringContainsString('Owner, in chat', $html);
        $this->assertStringContainsString('Checked for unusual activity', $html);
        $this->assertStringContainsString('more Wi-Fi codes used than sales would explain', $html);
        $this->assertStringContainsString('Barista AI, on its own', $html);

        // Only the page's own content: the chat widget in the layout carries
        // tool names as keys for its "working on…" labels on every admin page.
        $start = strpos($html, 'Agent Activity</span>');
        $content = substr($html, $start, strrpos($html, '</section>') - $start);

        foreach (['getAnomalySignals', 'voucher_revenue_divergence', 'blockSites', 'Barista AI (scheduled)', 'EXECUTED'] as $jargon) {
            $this->assertStringNotContainsString($jargon, $content, "page still shows \"{$jargon}\"");
        }
    }

    public function test_pending_actions_come_first_with_approve_and_decline(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->audit('blockSites', 'executed', ['success' => true, 'message' => 'done']);
        $this->audit('generateVoucherBatch', 'proposed', [], ['tier' => 'free', 'duration_minutes' => 60, 'quantity' => 10]);

        $response = $this->actingAs($admin)->get(route('admin.ai.actions.index'))->assertOk();

        $response->assertSeeInOrder(['Waiting for your OK', 'Wants to create Wi-Fi vouchers', 'Approve', 'Decline', 'What it did', 'Blocked websites for guests']);
        $response->assertSee('Speed tier: free · Minutes each: 60 · How many: 10', false);
    }

    public function test_routine_lookups_are_hidden_until_asked_for(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->audit('restockIngredient', 'executed', ['success' => true, 'message' => 'Added 10 box to Milk.']);
        $this->audit('getSalesSummary', 'executed', ['success' => true, 'message' => "Today's sales: PHP 0.00"]);
        $this->audit('None', 'rejected', ['message' => "Tool 'None' is not available."]);

        $this->actingAs($admin)->get(route('admin.ai.actions.index'))
            ->assertOk()
            ->assertSee('Restocked an ingredient')
            ->assertDontSee('Checked sales')
            ->assertSee('Show routine checks (2)');

        $this->actingAs($admin)->get(route('admin.ai.actions.index', ['show' => 'all']))
            ->assertOk()
            ->assertSee('Checked sales')
            ->assertSee('Hide routine checks');
    }

    public function test_warning_codes_and_snake_case_never_reach_the_owner(): void
    {
        $entry = new AgentActivityEntry($this->audit('blockDevice', 'rejected', [], ['mac_address' => 'AA:BB', 'reason' => 'repeat_mac_abuse detected']));

        $this->assertSame('Wants to block a device from the Wi-Fi', $entry->title());
        $this->assertSame('Device ID: AA:BB · Reason: one device used several vouchers detected', $entry->detail());

        $failed = new AgentActivityEntry($this->audit('setSessionBandwidthTier', 'failed', ['message' => 'Provide either voucher_code or ip_address.']));
        $this->assertSame('Provide either voucher code or ip address.', $failed->detail());
        $this->assertSame('Didn’t work', $failed->status()['label']);
    }

    public function test_empty_state_explains_what_will_appear(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.ai.actions.index'))
            ->assertOk()
            ->assertSee('Nothing here yet');
    }
}
