<?php

namespace Tests\Feature\Agent;

use App\Models\Voucher;
use App\Services\Agent\Tools\CheckMySessionTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CheckMySessionTool's entire safety property is that it only ever resolves
 * a session from $context (the caller's own IP/MAC, set by the controller
 * from the real request) — never from model-supplied $arguments, so a guest
 * can't use it to probe another device's session. That property was
 * previously pinned only by a docblock; these tests exercise it directly,
 * including the mac_address_hash lookup path (mac_address is an encrypted
 * column — see HasHashedMacAddress).
 */
class CheckMySessionToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_context_at_all_fails(): void
    {
        $result = app(CheckMySessionTool::class)->execute([], null, []);

        $this->assertFalse($result->success);
    }

    public function test_no_matching_voucher_reports_no_active_session(): void
    {
        $result = app(CheckMySessionTool::class)->execute([], null, ['ip' => '192.168.2.50']);

        $this->assertTrue($result->success);
        $this->assertFalse($result->data['active']);
    }

    public function test_resolves_by_ip_from_context(): void
    {
        Voucher::create([
            'code' => 'LAWA-BYIP', 'duration_minutes' => 120, 'is_used' => true,
            'used_at' => now()->subMinutes(10), 'ip_address' => '192.168.2.51',
        ]);

        $result = app(CheckMySessionTool::class)->execute([], null, ['ip' => '192.168.2.51']);

        $this->assertTrue($result->success);
        $this->assertTrue($result->data['active']);
        $this->assertArrayHasKey('minutes_left', $result->data);
    }

    public function test_resolves_by_mac_from_context_via_hashed_lookup(): void
    {
        Voucher::create([
            'code' => 'LAWA-BYMAC', 'duration_minutes' => 120, 'is_used' => true,
            'used_at' => now()->subMinutes(10), 'ip_address' => '192.168.2.52', 'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ]);

        // Different IP than what's stored, so only the MAC can match — proves
        // the mac_address_hash WHERE clause actually works post-encryption.
        $result = app(CheckMySessionTool::class)->execute([], null, ['ip' => '10.0.0.99', 'mac' => 'AA:BB:CC:DD:EE:FF']);

        $this->assertTrue($result->success);
        $this->assertTrue($result->data['active']);
    }

    public function test_a_model_supplied_ip_argument_is_ignored(): void
    {
        // Someone else's active session — the tool must not resolve it just
        // because the model happened to pass its IP as an "argument".
        Voucher::create([
            'code' => 'LAWA-OTHER', 'duration_minutes' => 120, 'is_used' => true,
            'used_at' => now()->subMinutes(10), 'ip_address' => '192.168.2.99',
        ]);

        $result = app(CheckMySessionTool::class)->execute(['ip' => '192.168.2.99'], null, ['ip' => '192.168.2.53']);

        $this->assertTrue($result->success);
        $this->assertFalse($result->data['active']);
    }

    public function test_expired_voucher_reports_inactive(): void
    {
        Voucher::create([
            'code' => 'LAWA-GONE', 'duration_minutes' => 30, 'is_used' => true,
            'used_at' => now()->subHours(3), 'ip_address' => '192.168.2.54',
        ]);

        $result = app(CheckMySessionTool::class)->execute([], null, ['ip' => '192.168.2.54']);

        $this->assertTrue($result->success);
        $this->assertFalse($result->data['active']);
        $this->assertStringContainsString('expired', $result->message);
    }
}
