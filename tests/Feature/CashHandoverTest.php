<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use App\Notifications\SystemAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Evaluators (Oct 28, R1 40:23, R2 00:00): nobody verifies their own cash
 * count. The opener checks the drawer against the last closing count, and
 * the closer has to actually count it.
 */
class CashHandoverTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create(['role' => 'staff']);
        $closer = User::factory()->create(['role' => 'admin', 'name' => 'Owner Ana']);
        Shift::create(['user_id' => $closer->id, 'starting_cash' => 500, 'expected_cash' => 1000, 'ending_cash' => 1000,
            'status' => 'closed', 'opened_at' => now()->subDay(), 'closed_at' => now()->subHours(12)]);
    }

    public function test_the_opener_sees_the_last_closing_count(): void
    {
        $this->actingAs($this->staff)->get(route('pos'))->assertOk()
            ->assertSee('Last closing count')->assertSee('₱1,000.00')->assertSee('Owner Ana');
    }

    public function test_a_matching_opening_count_starts_quietly(): void
    {
        Notification::fake();

        $this->actingAs($this->staff)->post(route('shift.start'), ['starting_cash' => 1000])->assertRedirect(route('pos'));

        $this->assertDatabaseHas('shifts', ['user_id' => $this->staff->id, 'status' => 'open']);
        Notification::assertNothingSent();
    }

    public function test_a_different_opening_count_needs_a_reason_and_tells_the_owner(): void
    {
        Notification::fake();
        $owner = User::where('name', 'Owner Ana')->first();

        $this->actingAs($this->staff)->post(route('shift.start'), ['starting_cash' => 800])->assertSessionHasErrors('opening_note');
        $this->assertDatabaseMissing('shifts', ['user_id' => $this->staff->id]);

        $this->actingAs($this->staff)->post(route('shift.start'), ['starting_cash' => 800, 'opening_note' => 'Owner took 200 for ice'])->assertRedirect(route('pos'));
        $this->assertStringContainsString('Owner took 200 for ice', Shift::where('user_id', $this->staff->id)->value('notes'));
        Notification::assertSentTo($owner, SystemAlert::class, fn ($n) => str_contains($n->toArray($owner)['message'], '₱200.00'));
    }

    public function test_closing_has_no_fill_in_the_expected_amount_shortcut(): void
    {
        $shift = Shift::create(['user_id' => $this->staff->id, 'starting_cash' => 1000, 'status' => 'open', 'opened_at' => now()]);

        $this->actingAs($this->staff)->get(route('shift.closing-report', $shift))->assertOk()
            ->assertDontSee('Use Expected Amount')->assertSee('Count the bills and coins');
    }
}
