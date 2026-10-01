<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An order the kitchen hasn't finished within the owner's reminder time is
 * announced on the staff screens, so nobody has to come and ask for it.
 */
class OrderWaitReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->create(['role' => 'staff']);
    }

    private function order(int $minutesAgo, string $status = 'pending', array $items = [['Latte', 'product', 2]], string $type = 'dine_in'): Sale
    {
        $sale = Sale::create([
            'transaction_number' => 'TRN-'.strtoupper(uniqid()),
            'total_amount' => 100,
            'status' => $status,
            'payment_method' => 'Cash',
            'order_type' => $type,
            'user_id' => $this->staff->id,
        ]);
        $sale->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->save();

        foreach ($items as [$name, $itemType, $qty]) {
            SaleItem::create(['sale_id' => $sale->id, 'item_name' => $name, 'type' => $itemType, 'quantity' => $qty, 'price' => 50]);
        }

        return $sale;
    }

    private function waiting()
    {
        return $this->actingAs($this->staff)->getJson(route('orders.waiting'))->assertOk();
    }

    public function test_an_order_open_past_one_minute_is_reported_with_what_to_make(): void
    {
        $late = $this->order(2, 'preparing', [['Latte', 'product', 2], ['Waffle', 'product', 1], ['1 Hour(s) Wi-Fi', 'wifi', 1]], 'takeaway');
        $this->order(0); // just placed

        $orders = $this->waiting()->assertJsonPath('minutes', 1)->json('orders');

        $this->assertCount(1, $orders);
        $this->assertSame($late->id, $orders[0]['id']);
        $this->assertSame(substr($late->transaction_number, -4), $orders[0]['number']);
        $this->assertSame(2, $orders[0]['minutes']);
        $this->assertSame('Take away', $orders[0]['order_type']);
        $this->assertSame('2× Latte, 1× Waffle', $orders[0]['items']);
    }

    public function test_finished_wifi_only_and_long_forgotten_orders_are_not_reported(): void
    {
        $this->order(5, 'completed');
        $this->order(5, 'cancelled');
        $this->order(5, 'pending', [['1 Hour(s) Wi-Fi', 'wifi', 1]]);
        $this->order(60 * 8); // yesterday's leftover nobody closed

        $this->assertSame([], $this->waiting()->json('orders'));
    }

    public function test_the_owner_sets_the_reminder_time(): void
    {
        Setting::set('order_wait_alert_minutes', '3');
        $this->order(2);
        $this->assertSame([], $this->waiting()->json('orders'));

        $this->order(4);
        $this->assertCount(1, $this->waiting()->json('orders'));
    }

    public function test_the_store_settings_page_saves_the_reminder_time(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.settings.store'))->assertOk()->assertSee('name="order_wait_alert_minutes" value="1"', false);
        $this->actingAs($admin)->post(route('admin.settings.store.update'), ['order_wait_alert_minutes' => 0])->assertSessionHasErrors('order_wait_alert_minutes');
        $this->actingAs($admin)->post(route('admin.settings.store.update'), ['order_wait_alert_minutes' => 2])->assertSessionHasNoErrors();

        $this->assertSame('2', Setting::where('key', 'order_wait_alert_minutes')->value('value'));
    }

    public function test_the_reminder_is_on_staff_and_admin_screens_but_not_for_super_admin(): void
    {
        foreach (['admin', 'staff'] as $layout) {
            $this->assertStringContainsString("@include('partials.order-wait-reminder')", file_get_contents(resource_path("views/layouts/{$layout}.blade.php")));
        }

        $this->actingAs($this->staff);
        $this->assertStringContainsString('orderWaitReminder', view('partials.order-wait-reminder')->render());

        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->assertStringNotContainsString('orderWaitReminder', view('partials.order-wait-reminder')->render());
    }

    public function test_the_kitchen_display_marks_late_orders(): void
    {
        $this->order(3);
        $html = $this->actingAs($this->staff)->getJson(route('kds.data'))->json('html');
        $this->assertStringContainsString('Waiting too long', $html);

        Sale::query()->delete();
        $this->order(0);
        $html = $this->actingAs($this->staff)->getJson(route('kds.data'))->json('html');
        $this->assertStringNotContainsString('Waiting too long', $html);
    }
}
