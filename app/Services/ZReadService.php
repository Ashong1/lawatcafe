<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use App\Models\ZReading;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The daily Z-Read: closes a business day once every shift is closed. Its
 * number counts up without gaps, it carries the running grand total of every
 * sale ever made, and after it the day's sales can't be voided.
 */
class ZReadService
{
    public function take(Carbon $day, User $by): ZReading
    {
        return DB::transaction(function () use ($day, $by) {
            if (ZReading::whereDate('business_date', $day)->lockForUpdate()->exists()) {
                throw new RuntimeException('The Z-Read for '.$day->format('M j').' was already taken.');
            }
            if (Shift::where('status', 'open')->exists()) {
                throw new RuntimeException('A shift is still open. Close every shift before taking the Z-Read.');
            }

            $figures = Shift::salesFigures(fn () => Sale::whereDate('created_at', $day));

            try {
                return $this->create($day, $by, $figures);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                throw new RuntimeException('Someone else just took the Z-Read. Refresh to see it.');
            }
        });
    }

    private function create(Carbon $day, User $by, array $figures): ZReading
    {
        return ZReading::create([
                'number' => (int) ZReading::max('number') + 1,
                'business_date' => $day->toDateString(),
                'grand_total' => (float) Sale::revenue()->where('created_at', '<=', $day->copy()->endOfDay())->sum('total_amount'),
                'first_transaction' => $figures['first_transaction'],
                'last_transaction' => $figures['last_transaction'],
                'totals' => $figures,
                'user_id' => $by->id,
            ]);
    }
}
