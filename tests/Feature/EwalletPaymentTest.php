<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * E-wallet payments: the owner uploads the shop's own GCash / Maya / QR Ph
 * receive QR; the register shows it, and the cashier records the transfer's
 * reference number.
 */
class EwalletPaymentTest extends TestCase
{
    use RefreshDatabase;

    // A real 1x1 PNG: UploadedFile::fake()->image() needs GD, which this server lacks.
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function qr(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('gcash-qr.png', base64_decode(self::PNG));
    }

    private function setUpGcash(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.settings.payment-qr.update', 'gcash'), ['qr' => $this->qr(), 'account_name' => 'JUAN D. CRUZ'])
            ->assertSessionHas('success');
    }

    private function sell(User $staff, Shift $shift, array $payment): TestResponse
    {
        $product = Product::firstOrCreate(['name' => 'Americano'], ['category' => 'Coffee Based', 'price' => 100, 'status' => 'Active']);

        return $this->actingAs($staff)->postJson(route('pos.checkout'), array_merge([
            'total_amount' => 100,
            'amount_received' => 100,
            'cart' => [['id' => $product->id, 'name' => 'Americano', 'category' => 'Coffee Based', 'type' => 'product', 'quantity' => 1, 'price' => 100, 'variant' => null]],
            'order_type' => 'dine_in',
            'shift_id' => $shift->id,
        ], $payment));
    }

    private function staffWithShift(): array
    {
        $staff = User::factory()->create(['role' => 'staff']);

        return [$staff, Shift::create(['user_id' => $staff->id, 'starting_cash' => 500, 'status' => 'open', 'opened_at' => now()])];
    }

    public function test_owner_uploads_a_qr_and_the_register_offers_it(): void
    {
        [$staff] = $this->staffWithShift();
        $this->actingAs($staff)->get(route('pos'))->assertDontSee('gcash-', false);

        $this->setUpGcash();
        Storage::disk('public')->assertExists(Setting::get('payment_qr_gcash'));

        $this->actingAs($staff)->get(route('pos'))->assertOk()
            ->assertSee(basename(Setting::get('payment_qr_gcash')), false)
            ->assertSee('JUAN D. CRUZ', false);
    }

    public function test_staff_cannot_change_payment_qrs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->post(route('admin.settings.payment-qr.update', 'gcash'), ['qr' => $this->qr()]);

        $this->assertNull(Setting::get('payment_qr_gcash'));
    }

    public function test_an_e_wallet_sale_needs_a_reference_number_and_records_it(): void
    {
        $this->setUpGcash();
        [$staff, $shift] = $this->staffWithShift();

        $this->sell($staff, $shift, ['payment_method' => 'GCash'])
            ->assertStatus(422)->assertJsonPath('message', 'Type the reference number from the customer\'s "sent" screen.');

        $this->sell($staff, $shift, ['payment_method' => 'GCash', 'payment_reference' => ' 1234 567 890123 ', 'amount_received' => 500])
            ->assertOk()->assertJsonPath('success', true);

        $sale = Sale::firstOrFail();
        $this->assertSame('GCash', $sale->payment_method);
        $this->assertSame('1234 567 890123', $sale->payment_reference);
        // An e-wallet transfer is the exact total: no change.
        $this->assertEquals(100, $sale->amount_received);
    }

    public function test_a_wallet_that_is_not_set_up_is_refused(): void
    {
        [$staff, $shift] = $this->staffWithShift();

        $this->sell($staff, $shift, ['payment_method' => 'Maya', 'payment_reference' => '123456'])->assertStatus(422);
        $this->assertSame(0, Sale::count());
    }

    public function test_cash_sales_work_as_before(): void
    {
        $this->setUpGcash();
        [$staff, $shift] = $this->staffWithShift();

        $this->sell($staff, $shift, ['payment_method' => 'Cash', 'amount_received' => 200])->assertOk();
        $this->assertNull(Sale::firstOrFail()->payment_reference);
    }

    public function test_the_shift_report_keeps_e_wallet_money_out_of_the_drawer(): void
    {
        $this->setUpGcash();
        [$staff, $shift] = $this->staffWithShift();
        $this->sell($staff, $shift, ['payment_method' => 'Cash'])->assertOk();
        $this->sell($staff, $shift, ['payment_method' => 'GCash', 'payment_reference' => '998877'])->assertOk();

        $response = $this->actingAs($staff)->get(route('shift.closing-report', $shift))->assertOk();

        $summary = $response->viewData('summary');
        $this->assertEquals(100, $summary['cash_sales']);
        $this->assertEquals(['GCash' => 100.0], $summary['ewallet_sales']);
        $this->assertEquals(200, $summary['total_sales']);
        $response->assertSee('in the shop\'s account, not the drawer', false);
    }

    public function test_turning_a_wallet_off_removes_it_from_the_register(): void
    {
        $this->setUpGcash();
        $path = Setting::get('payment_qr_gcash');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('admin.settings.payment-qr.destroy', 'gcash'))->assertSessionHas('success');

        Storage::disk('public')->assertMissing($path);
        [$staff, $shift] = $this->staffWithShift();
        $this->sell($staff, $shift, ['payment_method' => 'GCash', 'payment_reference' => '123456'])->assertStatus(422);
    }

    public function test_unknown_wallet_is_404(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.settings.payment-qr.update', 'paypal'), ['qr' => $this->qr()])
            ->assertNotFound();
    }
}
