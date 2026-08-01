<?php

namespace Tests\Feature\Agent;

use App\Models\Ingredient;
use App\Models\User;
use App\Services\Agent\ToolCallOrchestrator;
use App\Services\Agent\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the two paired changes needed to raise MAX_ROUND_TRIPS safely:
 * the loop now tolerates more rounds (3 -> 5), and large tool results no
 * longer re-enter message history uncapped on every remaining round.
 */
class ToolCallOrchestratorReasoningDepthTest extends TestCase
{
    use RefreshDatabase;

    private function sse(array $chunk): string
    {
        return 'data: '.json_encode($chunk)."\n\n";
    }

    private function geminiFunctionCallResponse(string $name, array $args): string
    {
        return $this->sse([
            'candidates' => [[
                'content' => ['parts' => [
                    ['functionCall' => ['name' => $name, 'args' => $args]],
                ]],
            ]],
        ]);
    }

    private function geminiTextResponse(string $text): string
    {
        return $this->sse(['candidates' => [['content' => ['parts' => [['text' => $text]]]]]]);
    }

    public function test_round_trips_now_allow_five_tool_calls_before_giving_up(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Always asks for another checkStockLevels call — never returns
        // plain text, so the loop only stops when MAX_ROUND_TRIPS runs out.
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->geminiFunctionCallResponse('checkStockLevels', []), 200)
                ->push($this->geminiFunctionCallResponse('checkStockLevels', []), 200)
                ->push($this->geminiFunctionCallResponse('checkStockLevels', []), 200)
                ->push($this->geminiFunctionCallResponse('checkStockLevels', []), 200)
                ->push($this->geminiFunctionCallResponse('checkStockLevels', []), 200),
        ]);

        $orchestrator = app(ToolCallOrchestrator::class);
        $result = $orchestrator->run([['role' => 'user', 'content' => 'check stock repeatedly']], ToolRegistry::AUDIENCE_ADMIN, $admin);

        $this->assertCount(5, $result['executed']);
        $this->assertStringContainsString('unable to finish', $result['reply']);
        Http::assertSentCount(5);
    }

    public function test_large_tool_results_are_truncated_before_reentering_message_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // 25 low-stock ingredients — more than the 20-item cap.
        for ($i = 1; $i <= 25; $i++) {
            Ingredient::create(['name' => "Ingredient {$i}", 'current_stock' => 1, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock']);
        }

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push($this->geminiFunctionCallResponse('checkStockLevels', []), 200)
                ->push($this->geminiTextResponse('Noted.'), 200),
        ]);

        $orchestrator = app(ToolCallOrchestrator::class);
        $result = $orchestrator->run([['role' => 'user', 'content' => 'any low stock?']], ToolRegistry::AUDIENCE_ADMIN, $admin);

        // The full, untruncated result must still reach the caller/UI —
        // truncation only applies to what's re-sent to the model.
        $this->assertCount(25, $result['executed'][0]['result']['data']['ingredients']);

        // But the second request (the one carrying the tool result back to
        // the model) must have a capped ingredients list.
        Http::assertSent(function ($request) {
            $contents = $request->data()['contents'] ?? [];
            $functionResponses = collect($contents)->filter(fn ($c) => ($c['role'] ?? null) === 'function');

            if ($functionResponses->isEmpty()) {
                return false;
            }

            $raw = $functionResponses->first()['parts'][0]['functionResponse']['response']['result'] ?? null;
            $decoded = json_decode($raw, true);

            return is_array($decoded['data']['ingredients'] ?? null)
                && count($decoded['data']['ingredients']) <= 21;
        });
    }
}
