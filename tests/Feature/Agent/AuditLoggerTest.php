<?php

namespace Tests\Feature\Agent;

use App\Models\User;
use App\Services\Agent\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_persists_an_ai_initiated_action(): void
    {
        $user = User::factory()->create();
        $logger = app(AuditLogger::class);

        $audit = $logger->record(
            'checkStockLevels',
            ['foo' => 'bar'],
            ['success' => true, 'message' => 'ok'],
            'ai',
            $user->id,
            'executed',
        );

        $this->assertDatabaseHas('ai_action_audits', [
            'id' => $audit->id,
            'tool_name' => 'checkStockLevels',
            'actor_type' => 'ai',
            'actor_user_id' => $user->id,
            'status' => 'executed',
        ]);
        $this->assertSame(['foo' => 'bar'], $audit->fresh()->input_params);
    }

    public function test_record_persists_a_human_initiated_action_with_no_actor(): void
    {
        $logger = app(AuditLogger::class);

        $audit = $logger->record('someTool', [], [], 'human', null, 'executed');

        $this->assertDatabaseHas('ai_action_audits', [
            'id' => $audit->id,
            'actor_type' => 'human',
            'actor_user_id' => null,
        ]);
    }

    public function test_mark_executed_updates_status_result_and_approver(): void
    {
        $approver = User::factory()->create();
        $logger = app(AuditLogger::class);
        $audit = $logger->record('restockIngredient', [], [], 'ai', null, 'proposed');

        $logger->markExecuted($audit, ['success' => true, 'message' => 'done'], $approver);

        $audit->refresh();
        $this->assertSame('executed', $audit->status);
        $this->assertSame($approver->id, $audit->approved_by_user_id);
        $this->assertSame(['success' => true, 'message' => 'done'], $audit->result);
    }

    public function test_mark_rejected_updates_status_and_approver(): void
    {
        $rejector = User::factory()->create();
        $logger = app(AuditLogger::class);
        $audit = $logger->record('blockDevice', [], [], 'ai', null, 'proposed');

        $logger->markRejected($audit, $rejector);

        $audit->refresh();
        $this->assertSame('rejected', $audit->status);
        $this->assertSame($rejector->id, $audit->approved_by_user_id);
    }
}
