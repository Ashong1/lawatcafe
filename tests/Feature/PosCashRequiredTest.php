<?php

namespace Tests\Feature;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owner (Oct 30): a cash order went through with nothing typed in "Cash received". */
class PosCashRequiredTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cash_order_needs_the_cash_received(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        Shift::create(['user_id' => $staff->id, 'starting_cash' => 0, 'status' => 'open', 'opened_at' => now()]);

        $html = $this->actingAs($staff)->get(route('pos'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Blank = exact amount', $html);
        $this->assertStringNotContainsString('this.amountTendered || this.grandTotal', $html);
        $this->assertStringContainsString('@click="amountTendered = grandTotal"', $html);
        $this->assertStringContainsString("'Type the cash received'", $html);
    }
}
