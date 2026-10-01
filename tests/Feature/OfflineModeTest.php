<?php

namespace Tests\Feature;

use App\Mail\PurchaseOrderRequest;
use App\Models\Ingredient;
use App\Models\PurchaseOrderDraft;
use App\Models\Supplier;
use App\Services\AiBudget;
use App\Services\AIService;
use App\Services\InternetStatus;
use App\Services\NetworkHealthService;
use App\Services\SupplierOrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The shop keeps working through an internet outage: internet-only features
 * fail at once with a plain explanation, and email waits instead of being lost.
 */
class OfflineModeTest extends TestCase
{
    use RefreshDatabase;

    private function internetCheck(string $status, ?string $checkedAt = null): void
    {
        Cache::put(NetworkHealthService::LATEST_KEY, [
            'checked_at' => $checkedAt ?? now()->toIso8601String(),
            'overall' => $status,
            'checks' => ['internet' => ['label' => 'Internet link', 'status' => $status, 'summary' => '', 'details' => []]],
        ], 600);
    }

    public function test_status_follows_the_latest_fresh_health_check(): void
    {
        $this->assertFalse(InternetStatus::isDown(), 'no check yet');

        $this->internetCheck('ok');
        $this->assertFalse(InternetStatus::isDown());

        $this->internetCheck('fail');
        $this->assertTrue(InternetStatus::isDown());

        $this->internetCheck('fail', now()->subMinutes(10)->toIso8601String());
        $this->assertFalse(InternetStatus::isDown(), 'a stale result proves nothing');
    }

    public function test_ai_chat_gives_up_at_once_without_calling_out(): void
    {
        config(['services.openrouter.key' => 'test-key']);
        $this->internetCheck('fail');
        Http::fake();

        $started = microtime(true);
        $result = app(AIService::class)->chatWithToolsStreaming([['role' => 'user', 'content' => 'hi']], [], fn () => null);

        $this->assertNull($result);
        $this->assertLessThan(1.0, microtime(true) - $started);
        Http::assertNothingSent();
    }

    public function test_background_ai_jobs_wait_for_the_internet(): void
    {
        config(['services.openrouter.key' => 'test-key']);
        $this->internetCheck('fail');
        Http::fake();

        $this->assertFalse(app(AiBudget::class)->backgroundMaySpend());
        Http::assertNothingSent();
    }

    public function test_staff_and_admin_see_an_outage_banner(): void
    {
        $this->assertSame('', trim(view('partials.offline-banner')->render()));

        $this->internetCheck('fail');
        $this->assertStringContainsString('The internet is down.', view('partials.offline-banner')->render());

        foreach (['admin', 'staff'] as $layout) {
            $this->assertStringContainsString("@include('partials.offline-banner')", file_get_contents(resource_path("views/layouts/{$layout}.blade.php")));
        }
    }

    public function test_guests_on_the_portal_are_told_the_provider_is_down(): void
    {
        $notice = 'Our internet provider is down right now';

        $this->get(route('portal.index'))->assertOk()->assertDontSee($notice, false);

        $this->internetCheck('fail');
        $this->get(route('portal.index'))->assertOk()->assertSee($notice, false);
    }

    public function test_a_purchase_order_sent_offline_is_queued_and_says_so(): void
    {
        Mail::fake();
        $this->internetCheck('fail');
        $ingredient = Ingredient::create(['name' => 'Milk', 'current_stock' => 50, 'unit' => 'ml', 'low_stock_threshold' => 500, 'status' => 'Low Stock']);
        $supplier = Supplier::create(['name' => 'Acme Dairy', 'email' => 'orders@acmedairy.test']);
        $draft = PurchaseOrderDraft::create(['ingredient_id' => $ingredient->id, 'supplier_id' => $supplier->id, 'suggested_quantity' => 1000, 'status' => 'draft', 'created_by_actor_type' => 'ai']);

        $result = app(SupplierOrderService::class)->sendPurchaseOrder($draft);

        $this->assertStringContainsString("will go out as soon as it's back", $result['message']);
        Mail::assertQueued(PurchaseOrderRequest::class);
    }

    public function test_queued_mail_keeps_retrying_through_an_outage(): void
    {
        $mail = new PurchaseOrderRequest(new PurchaseOrderDraft);

        $this->assertInstanceOf(ShouldQueue::class, $mail);
        $this->assertSame(300, $mail->backoff);
        $this->assertTrue($mail->retryUntil() > now()->addDays(2));
    }
}
