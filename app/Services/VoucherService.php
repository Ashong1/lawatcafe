<?php

namespace App\Services;

use App\Models\Voucher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class VoucherService
{
    /**
     * Batch generate unique vouchers with custom quantity and duration.
     *
     * @return array{count: int, codes: string[]}
     */
    public function generateBatch(int $quantity, int $durationMinutes, ?int $actorUserId = null, string $actorType = 'human'): array
    {
        $codes = [];

        while (count($codes) < $quantity) {
            $code = 'LAWA-' . strtoupper(Str::random(4));

            // Collision check: only create if the code doesn't already exist
            if (!Voucher::where('code', $code)->exists()) {
                Voucher::create([
                    'code' => $code,
                    'duration_minutes' => $durationMinutes,
                    'is_used' => false,
                ]);
                $codes[] = $code;
            }
        }

        Cache::forget('dashboard_stats');

        return ['count' => count($codes), 'codes' => $codes];
    }

    public function deleteVouchers(array $ids): int
    {
        $count = Voucher::whereIn('id', $ids)->delete();
        Cache::forget('dashboard_stats');
        return $count;
    }

    public function purgeUsed(): int
    {
        // Only purge vouchers whose allotted duration has actually elapsed.
        // Deleting a still-active voucher orphans its live OPNsense session:
        // EnforceSessionLimits can no longer find it to expire it, and the
        // sessions UI loses the ability to show real time-left for it.
        $expired = Voucher::where('is_used', true)
            ->get()
            ->filter(fn ($v) => $v->used_at && now()->greaterThan($v->used_at->copy()->addMinutes($v->duration_minutes)));

        $usedCount = $expired->count();
        Voucher::whereIn('id', $expired->pluck('id'))->delete();
        Cache::forget('dashboard_stats');
        return $usedCount;
    }
}
