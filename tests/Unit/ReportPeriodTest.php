<?php

namespace Tests\Unit;

use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportPeriodTest extends TestCase
{
    private function period(string $period, string $date): ReportPeriod
    {
        return ReportPeriod::fromRequest(Request::create('/', 'GET', ['period' => $period, 'date' => $date]));
    }

    public function test_a_week_runs_monday_to_sunday(): void
    {
        $p = $this->period('week', '2026-10-30');

        $this->assertSame('2026-10-26 00:00:00', $p->start->toDateTimeString());
        $this->assertSame('2026-11-01 23:59:59', $p->end->toDateTimeString());
        $this->assertSame('Oct 26 – Nov 1, 2026', $p->label());
        $this->assertCount(7, $p->buckets());
        $this->assertSame('day', $p->bucket());
    }

    public function test_bucket_counts_per_period(): void
    {
        $this->assertCount(24, $this->period('day', '2026-10-30')->buckets());
        $this->assertCount(29, $this->period('month', '2028-02-10')->buckets());
        $this->assertCount(12, $this->period('year', '2026-05-01')->buckets());
        $this->assertSame('month', $this->period('year', '2026-05-01')->bucket());
        $this->assertSame('October 2026', $this->period('month', '2026-10-30')->label());
        $this->assertSame('2026', $this->period('year', '2026-10-30')->label());
    }

    public function test_previous_and_next(): void
    {
        Carbon::setTestNow('2026-10-30 10:00:00');

        $month = $this->period('month', '2026-10-30');
        $this->assertSame('2026-09-01', $month->previous()->start->toDateString());
        $this->assertNull($month->next());
        $this->assertSame('2026-10-01', $month->previous()->next()->start->toDateString());
    }

    public function test_bad_input_falls_back_to_today(): void
    {
        Carbon::setTestNow('2026-10-30 10:00:00');

        $p = $this->period('fortnight', 'not-a-date');

        $this->assertSame('day', $p->period);
        $this->assertSame('2026-10-30 00:00:00', $p->start->toDateTimeString());
    }
}
