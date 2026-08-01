<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The self-service GCash tab was removed from the portal UI (system is
 * cash-only now). portal.verify-payment is kept as a route so old bookmarks/
 * QR codes don't 404, but must reject every request rather than run the old
 * EwalletPayment-matching/voucher-issuing flow.
 */
class CaptivePortalVerifyPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_with_a_redirect_for_the_plain_form_fallback(): void
    {
        $response = $this->post(route('portal.verify-payment'), ['reference_number' => 'GC-ANYTHING']);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'This location only accepts cash. Please pay at the counter.');
    }

    public function test_rejects_with_a_json_error_for_the_fetch_path(): void
    {
        $response = $this->postJson(route('portal.verify-payment'), ['reference_number' => 'GC-ANYTHING']);

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'message' => 'This location only accepts cash. Please pay at the counter.']);
    }
}
