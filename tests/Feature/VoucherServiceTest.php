<?php

namespace Tests\Feature;

use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_batch_creates_the_requested_number_of_unique_vouchers(): void
    {
        $service = app(VoucherService::class);

        $result = $service->generateBatch(5, 60);

        $this->assertSame(5, $result['count']);
        $this->assertCount(5, $result['codes']);
        $this->assertSame(5, Voucher::whereIn('code', $result['codes'])->count());
        $this->assertSame(count(array_unique($result['codes'])), count($result['codes']));

        foreach ($result['codes'] as $code) {
            $voucher = Voucher::where('code', $code)->first();
            $this->assertSame(60, $voucher->duration_minutes);
            $this->assertFalse((bool) $voucher->is_used);
        }
    }

    public function test_generate_batch_avoids_collisions_with_existing_codes(): void
    {
        Voucher::create(['code' => 'LAWA-AAAA', 'duration_minutes' => 60, 'is_used' => false]);
        $service = app(VoucherService::class);

        $result = $service->generateBatch(3, 60);

        $this->assertNotContains('LAWA-AAAA', $result['codes']);
        $this->assertSame(4, Voucher::count());
    }

    public function test_purge_used_only_removes_expired_used_vouchers(): void
    {
        Voucher::create(['code' => 'LAWA-EXPIRED', 'duration_minutes' => 60, 'is_used' => true, 'used_at' => now()->subMinutes(90)]);
        Voucher::create(['code' => 'LAWA-ACTIVE', 'duration_minutes' => 60, 'is_used' => true, 'used_at' => now()]);
        Voucher::create(['code' => 'LAWA-FREE', 'duration_minutes' => 60, 'is_used' => false]);

        $service = app(VoucherService::class);
        $purged = $service->purgeUsed();

        $this->assertSame(1, $purged);
        $this->assertSame(2, Voucher::count());
        $this->assertDatabaseMissing('vouchers', ['code' => 'LAWA-EXPIRED']);
        $this->assertDatabaseHas('vouchers', ['code' => 'LAWA-ACTIVE']);
        $this->assertDatabaseHas('vouchers', ['code' => 'LAWA-FREE']);
    }

    public function test_purge_used_does_not_delete_a_still_active_session_voucher(): void
    {
        // Regression guard: purging must never orphan a voucher whose guest
        // is still inside their paid duration — doing so breaks both
        // EnforceSessionLimits (can't find it to expire it) and the
        // sessions UI (shows as "orphaned" with no real time-left).
        Voucher::create(['code' => 'LAWA-INUSE', 'duration_minutes' => 120, 'is_used' => true, 'used_at' => now()->subMinutes(10)]);

        $service = app(VoucherService::class);
        $purged = $service->purgeUsed();

        $this->assertSame(0, $purged);
        $this->assertDatabaseHas('vouchers', ['code' => 'LAWA-INUSE']);
    }
}
