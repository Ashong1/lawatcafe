<?php

namespace Tests\Feature;

use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptivePortalAuthenticateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_code_gets_a_not_found_message(): void
    {
        $response = $this->post(route('portal.authenticate'), ['passcode' => 'NOPE-0000']);

        $response->assertSessionHas('error', "That code doesn't match any voucher — double-check it against your receipt.");
    }

    public function test_already_used_code_gets_a_distinct_message(): void
    {
        Voucher::create([
            'code' => 'FREE-USED',
            'duration_minutes' => 60,
            'tier' => 'free',
            'is_used' => true,
            'used_at' => now(),
        ]);

        $response = $this->post(route('portal.authenticate'), ['passcode' => 'FREE-USED']);

        $response->assertSessionHas('error', 'This code has already been used.');
    }
}
