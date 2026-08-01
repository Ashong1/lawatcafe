<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Same reasoning as CaptivePortalVerifyPaymentTest — the receipt-OCR-to-
 * reference-number flow this endpoint fed only existed for the now-removed
 * GCash tab, so it must reject every request instead of running AI OCR.
 */
class CaptivePortalUploadReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_with_a_redirect_for_the_plain_form_fallback(): void
    {
        $response = $this->post(route('portal.upload'));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'This location only accepts cash. Please pay at the counter.');
    }

    public function test_rejects_with_a_json_error_for_the_fetch_path(): void
    {
        $response = $this->postJson(route('portal.upload'));

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'message' => 'This location only accepts cash. Please pay at the counter.']);
    }
}
