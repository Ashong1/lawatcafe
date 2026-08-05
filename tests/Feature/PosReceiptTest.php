<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PosReceiptTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Printing is off by default now, pending BIR registration — so these
     * tests, which are about what the receipt CONTAINS, have to switch it on
     * first. Whether it may be reached at all is PosReceiptBirGateTest's
     * subject.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('pos_receipt_printing_enabled', '1');
        Cache::forget('setting.pos_receipt_printing_enabled');
    }

    public function test_receipt_prints_the_voucher_code_when_the_sale_generated_one(): void
    {
        // Regression coverage: the portal tells guests their Wi-Fi passcode is
        // printed on the receipt (resources/views/portal/index.blade.php), but
        // the receipt template used to never render it at all.
        $staff = User::factory()->create(['role' => 'staff']);
        $sale = Sale::create([
            'transaction_number' => 'TRN-TEST-1',
            'total_amount' => 250,
            'amount_received' => 250,
            'status' => 'completed',
            'payment_method' => 'Cash',
            'order_type' => 'takeaway',
            'user_id' => $staff->id,
        ]);
        Voucher::create([
            'code' => 'FREE-RCPT',
            'duration_minutes' => 60,
            'tier' => 'free',
            'is_used' => false,
            'sale_id' => $sale->id,
        ]);

        $response = $this->actingAs($staff)->get(route('pos.receipt', $sale));

        $response->assertOk();
        $response->assertSee('FREE-RCPT');
    }

    public function test_receipt_omits_the_voucher_section_for_a_sale_with_no_wifi(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $sale = Sale::create([
            'transaction_number' => 'TRN-TEST-2',
            'total_amount' => 100,
            'amount_received' => 100,
            'status' => 'completed',
            'payment_method' => 'Cash',
            'order_type' => 'takeaway',
            'user_id' => $staff->id,
        ]);

        $response = $this->actingAs($staff)->get(route('pos.receipt', $sale));

        $response->assertOk();
        $response->assertDontSee('WI-FI PASSCODE');
    }
}
