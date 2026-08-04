<?php

namespace Tests\Feature\Agent;

use App\Models\Voucher;
use App\Services\Agent\Tools\LookupVoucherTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupVoucherToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_code_fails(): void
    {
        $result = app(LookupVoucherTool::class)->execute(['code' => ''], null);

        $this->assertFalse($result->success);
    }

    public function test_unknown_code_fails(): void
    {
        $result = app(LookupVoucherTool::class)->execute(['code' => 'LAWA-NOPE'], null);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('No voucher found', $result->message);
    }

    public function test_unused_voucher_reports_valid_and_unused(): void
    {
        Voucher::create(['code' => 'LAWA-FRESH', 'duration_minutes' => 60, 'is_used' => false]);

        $result = app(LookupVoucherTool::class)->execute(['code' => 'LAWA-FRESH'], null);

        $this->assertTrue($result->success);
        $this->assertSame('unused', $result->data['status']);
        $this->assertSame(60, $result->data['duration_minutes']);
    }

    public function test_used_and_still_active_voucher_reports_active(): void
    {
        Voucher::create([
            'code' => 'LAWA-ACTIVE', 'duration_minutes' => 120, 'is_used' => true,
            'used_at' => now()->subMinutes(10),
        ]);

        $result = app(LookupVoucherTool::class)->execute(['code' => 'LAWA-ACTIVE'], null);

        $this->assertTrue($result->success);
        $this->assertSame('active', $result->data['status']);
    }

    public function test_used_and_expired_voucher_reports_expired(): void
    {
        Voucher::create([
            'code' => 'LAWA-EXPIRED', 'duration_minutes' => 30, 'is_used' => true,
            'used_at' => now()->subHours(2),
        ]);

        $result = app(LookupVoucherTool::class)->execute(['code' => 'LAWA-EXPIRED'], null);

        $this->assertTrue($result->success);
        $this->assertSame('expired', $result->data['status']);
    }
}
