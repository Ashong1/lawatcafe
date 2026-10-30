<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The time span a report covers: ?period=day|week|month|year&date=YYYY-MM-DD.
 * Weeks run Monday to Sunday; boundaries follow the app timezone.
 */
final class ReportPeriod
{
    public const PERIODS = ['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly', 'year' => 'Yearly'];

    public readonly CarbonImmutable $start;

    public readonly CarbonImmutable $end;

    private function __construct(public readonly string $period, CarbonImmutable $date)
    {
        $unit = $period === 'day' ? 'day' : $period;
        $this->start = $date->startOf($unit);
        $this->end = $date->endOf($unit)->startOfSecond();
    }

    public static function fromRequest(Request $request): self
    {
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'day';

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', (string) $request->query('date'), config('app.timezone')) ?: null;
        } catch (\Throwable) {
            $date = null;
        }

        return new self($period, $date ?? CarbonImmutable::now(config('app.timezone')));
    }

    public static function of(string $period, CarbonImmutable $date): self
    {
        return new self($period, $date);
    }

    public function label(): string
    {
        return match ($this->period) {
            'day' => $this->start->format('M j, Y'),
            'week' => $this->start->format('M j').' – '.$this->end->format('M j, Y'),
            'month' => $this->start->format('F Y'),
            'year' => $this->start->format('Y'),
        };
    }

    public function previous(): self
    {
        return new self($this->period, $this->start->sub(1, $this->period));
    }

    /** Null when the next period hasn't started yet. */
    public function next(): ?self
    {
        $next = $this->start->add(1, $this->period);

        return $next->isFuture() ? null : new self($this->period, $next);
    }

    /** How the period is broken down: a day by hour, a year by month, the rest by day. */
    public function bucket(): string
    {
        return match ($this->period) {
            'day' => 'hour',
            'year' => 'month',
            default => 'day',
        };
    }

    /**
     * Every bucket in the period, key => label. Keys match what
     * ReportPeriod::bucketSql() produces: hour "00".."23", day "Y-m-d", month "Y-m".
     *
     * @return array<string, string>
     */
    public function buckets(): array
    {
        $buckets = [];
        for ($t = $this->start; $t <= $this->end; $t = $t->add(1, $this->bucket())) {
            [$key, $label] = match ($this->bucket()) {
                'hour' => [$t->format('H'), $t->format('g A')],
                'day' => [$t->format('Y-m-d'), $t->format($this->period === 'week' ? 'D, M j' : 'M j')],
                'month' => [$t->format('Y-m'), $t->format('M')],
            };
            $buckets[$key] = $label;
        }

        return $buckets;
    }

    /** SQL that turns $column into this period's bucket key, on MySQL or SQLite. */
    public function bucketSql(string $driver, string $column = 'created_at'): string
    {
        $formats = ['hour' => ['%H', '%H'], 'day' => ['%Y-%m-%d', '%Y-%m-%d'], 'month' => ['%Y-%m', '%Y-%m']];
        [$mysql, $sqlite] = $formats[$this->bucket()];

        return $driver === 'sqlite' ? "strftime('{$sqlite}', {$column})" : "DATE_FORMAT({$column}, '{$mysql}')";
    }
}
