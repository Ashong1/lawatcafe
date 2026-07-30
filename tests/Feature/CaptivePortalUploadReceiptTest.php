<?php

namespace Tests\Feature;

use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * portal.upload supports the same two response modes as verify-payment (see
 * CaptivePortalVerifyPaymentTest) — JSON for the fetch-based upload
 * (submitReceiptUpload() in portal/index.blade.php) and the original
 * redirect+session-flash for a plain <form> fallback. Zero test coverage
 * existed for this endpoint before this pass, despite it running an
 * external AI OCR call on unauthenticated, guest-facing input.
 */
class CaptivePortalUploadReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_readable_receipt_returns_the_reference_number_as_json(): void
    {
        Storage::fake('local');
        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('extractPaymentDetails')->once()->andReturn(['reference_number' => '1234567890']);
        });

        $response = $this->postJson(route('portal.upload'), [
            'receipt' => UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'reference_number' => '1234567890']);
    }

    public function test_a_readable_receipt_still_supports_the_redirect_fallback(): void
    {
        Storage::fake('local');
        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('extractPaymentDetails')->once()->andReturn(['reference_number' => '5551234567']);
        });

        $response = $this->post(route('portal.upload'), [
            'receipt' => UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('message');
    }

    public function test_an_unreadable_receipt_returns_a_json_error(): void
    {
        Storage::fake('local');
        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('extractPaymentDetails')->once()->andReturn(null);
        });

        $response = $this->postJson(route('portal.upload'), [
            'receipt' => UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg'),
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_the_temp_file_is_deleted_after_processing(): void
    {
        Storage::fake('local');
        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('extractPaymentDetails')->once()->andReturn(['reference_number' => '999']);
        });

        $this->postJson(route('portal.upload'), [
            'receipt' => UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg'),
        ]);

        $remaining = collect(Storage::disk('local')->allFiles('temp_receipts'));
        $this->assertTrue($remaining->isEmpty(), 'Temp receipt file should be cleaned up after OCR processing.');
    }

    public function test_a_non_image_file_is_rejected_by_validation(): void
    {
        Storage::fake('local');

        $response = $this->postJson(route('portal.upload'), [
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 100),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('receipt');
    }
}
