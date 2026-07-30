<?php

namespace Tests\Feature;

use App\Mail\ShiftAuditResult;
use App\Models\Shift;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Fires only when a closed shift's counted cash came up short of what was
 * expected (any shortfall, no minimum threshold) — a balanced or over-by
 * shift triggers nothing. Both the staff member and admins/owner get the
 * email + SystemAlert, per explicit user answer.
 */
class ShiftAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeClosedShift(User $staff, float $expected, float $ending): Shift
    {
        return Shift::create([
            'user_id' => $staff->id,
            'starting_cash' => $expected,
            'expected_cash' => $expected,
            'ending_cash' => $ending,
            'status' => 'closed',
            'opened_at' => now()->subHours(8),
            'closed_at' => now(),
        ]);
    }

    public function test_a_shortage_emails_and_notifies_both_staff_and_admins(): void
    {
        Mail::fake();
        Notification::fake();
        $this->mock(AIService::class, fn ($mock) => $mock->shouldReceive('summarizeShiftAudit')->once()->andReturn('The shift came up short.'));

        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $shift = $this->makeClosedShift($staff, expected: 500, ending: 480);

        app(\App\Services\ShiftAuditService::class)->auditShiftClose($shift);

        Mail::assertSent(ShiftAuditResult::class, fn ($mail) => $mail->hasTo($staff->email));
        Mail::assertSent(ShiftAuditResult::class, fn ($mail) => $mail->hasTo($admin->email));
        Notification::assertSentTo($staff, SystemAlert::class);
        Notification::assertSentTo($admin, SystemAlert::class);
    }

    public function test_a_balanced_shift_triggers_nothing(): void
    {
        Mail::fake();
        Notification::fake();
        $this->mock(AIService::class, fn ($mock) => $mock->shouldNotReceive('summarizeShiftAudit'));

        $staff = User::factory()->create(['role' => 'staff']);
        $shift = $this->makeClosedShift($staff, expected: 500, ending: 500);

        app(\App\Services\ShiftAuditService::class)->auditShiftClose($shift);

        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_an_overage_triggers_nothing(): void
    {
        Mail::fake();
        Notification::fake();
        $this->mock(AIService::class, fn ($mock) => $mock->shouldNotReceive('summarizeShiftAudit'));

        $staff = User::factory()->create(['role' => 'staff']);
        $shift = $this->makeClosedShift($staff, expected: 500, ending: 520);

        app(\App\Services\ShiftAuditService::class)->auditShiftClose($shift);

        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_email_still_sends_when_the_ai_summary_is_unavailable(): void
    {
        Mail::fake();
        Notification::fake();
        $this->mock(AIService::class, fn ($mock) => $mock->shouldReceive('summarizeShiftAudit')->andReturn(null));

        $staff = User::factory()->create(['role' => 'staff']);
        $shift = $this->makeClosedShift($staff, expected: 500, ending: 480);

        app(\App\Services\ShiftAuditService::class)->auditShiftClose($shift);

        Mail::assertSent(ShiftAuditResult::class, fn ($mail) => $mail->aiSummary === null);
    }
}
