<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * A POS that issues printed receipts or invoices to customers must be
 * registered and accredited with the BIR before it may do so. Lawa't Kape's is
 * not, so printing is withheld — the sale still records exactly as normal, it
 * simply produces no printed receipt.
 *
 * The switch back on is deliberately super_admin's alone: an admin runs the
 * shop, but whether this machine may print receipts follows the registration,
 * not the shop floor.
 */
class PosReceiptBirGateTest extends TestCase
{
    use RefreshDatabase;

    private function setPrinting(bool $on): void
    {
        Setting::set('pos_receipt_printing_enabled', $on ? '1' : '0');
        Cache::forget('setting.pos_receipt_printing_enabled');
    }

    private function makeSale(): Sale
    {
        return Sale::create([
            'transaction_number' => 'TRN-BIR-1',
            'total_amount' => 150,
            'status' => 'completed',
            'payment_method' => 'Cash',
            'order_type' => 'dine_in',
            'user_id' => User::factory()->create(['role' => 'staff'])->id,
        ]);
    }

    /** The default has to be OFF — printing first and registering later is the order that creates the problem. */
    public function test_receipt_printing_is_disabled_by_default(): void
    {
        $this->assertFalse(Setting::receiptPrintingEnabled());
    }

    /**
     * Enforced at the controller, not just by hiding buttons: the receipt view
     * auto-prints on load, so a bookmark or a history entry would otherwise
     * produce exactly the printed receipt this is meant to prevent.
     */
    public function test_the_receipt_url_is_refused_while_printing_is_off(): void
    {
        $this->setPrinting(false);
        $sale = $this->makeSale();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('pos.receipt', $sale))
            ->assertRedirect(route('pos.history'))
            ->assertSessionHas('error');
    }

    public function test_the_receipt_renders_once_printing_is_enabled(): void
    {
        $this->setPrinting(true);
        $sale = $this->makeSale();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('pos.receipt', $sale))
            ->assertOk();
    }

    public function test_the_print_button_is_absent_from_order_history_while_printing_is_off(): void
    {
        $this->setPrinting(false);
        $this->makeSale();

        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('pos.history'));

        $response->assertOk();
        $response->assertDontSee('Reprint Receipt', false);
        // The page's own description must not promise it either.
        $response->assertDontSee('reprint receipts', false);
    }

    public function test_the_print_button_returns_with_the_setting(): void
    {
        $this->setPrinting(true);
        $this->makeSale();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('pos.history'))
            ->assertSee('Reprint Receipt', false);
    }

    public function test_the_pos_hides_the_print_action_and_says_why(): void
    {
        $this->setPrinting(false);

        $response = $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('pos'));

        $response->assertOk();
        $response->assertDontSee("'/pos/receipt/' + saleId", false);
        // A cashier should not go hunting for a button that used to be there.
        $response->assertSee('pending BIR registration', false);
    }

    /**
     * The guest portal tells people their Wi-Fi code is "printed at the bottom
     * of your receipt". With no receipt printed, that sends them looking for a
     * piece of paper that never existed.
     */
    public function test_the_portal_stops_pointing_guests_at_a_receipt_that_is_not_printed(): void
    {
        $this->setPrinting(false);

        $response = $this->get(route('portal.index'));

        $response->assertOk();
        $response->assertSee('given to you at the counter', false);
        $response->assertDontSee('printed at the bottom of your receipt', false);
    }

    // --- Who may flip it ---------------------------------------------------

    public function test_a_super_admin_can_turn_printing_on(): void
    {
        $this->setPrinting(false);

        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->post(route('admin.settings.receipt-printing.update'), ['enabled' => '1'])
            ->assertRedirect();

        Cache::forget('setting.pos_receipt_printing_enabled');
        $this->assertTrue(Setting::receiptPrintingEnabled());
    }

    public function test_a_super_admin_can_turn_printing_back_off(): void
    {
        $this->setPrinting(true);

        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->post(route('admin.settings.receipt-printing.update'), ['enabled' => '0'])
            ->assertRedirect();

        Cache::forget('setting.pos_receipt_printing_enabled');
        $this->assertFalse(Setting::receiptPrintingEnabled());
    }

    /** A compliance switch an admin could flip is not a compliance switch. */
    public function test_an_admin_cannot_flip_the_compliance_switch(): void
    {
        $this->setPrinting(false);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.settings.receipt-printing.update'), ['enabled' => '1'])
            ->assertRedirect(route('dashboard'));

        Cache::forget('setting.pos_receipt_printing_enabled');
        $this->assertFalse(Setting::receiptPrintingEnabled());
    }

    public function test_staff_cannot_flip_the_compliance_switch(): void
    {
        $this->setPrinting(false);

        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->post(route('admin.settings.receipt-printing.update'), ['enabled' => '1'])
            ->assertRedirect(route('staff.dashboard'));

        Cache::forget('setting.pos_receipt_printing_enabled');
        $this->assertFalse(Setting::receiptPrintingEnabled());
    }

    /** The toggle is only offered to the account that may actually use it. */
    public function test_only_super_admin_sees_the_toggle_on_the_settings_page(): void
    {
        $this->setPrinting(false);

        $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->get(route('admin.settings.store'))
            ->assertSee('Enable Receipt Printing', false);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.settings.store'))
            ->assertDontSee('Enable Receipt Printing', false);
    }
}
