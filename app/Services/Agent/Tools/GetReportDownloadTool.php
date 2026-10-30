<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;

/**
 * Hands the owner a report file in the chat (owner, Oct 30: "send it through
 * the chat"). Read-only: it builds the same download links the Reports and
 * Orders pages use, which check the signed-in account as usual.
 */
class GetReportDownloadTool implements AgentTool
{
    private const REPORTS = [
        'sales' => ['reports.sales.csv', 'Sales report'],
        'stock' => ['reports.stock.csv', 'Stock report'],
        'orders' => ['pos.history.csv', 'Orders list'],
    ];

    public function name(): string
    {
        return 'getReportDownload';
    }

    public function description(): string
    {
        return 'Give the user a download (CSV, opens in Excel) of a report: sales (net sales by hour/day/month), stock (start, received, used, wasted, end per ingredient) or orders (every order with items, payment and cashier), for a day, week, month or year. Use whenever they ask to download, export, send or get a file of a report. The chat shows a Download button.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'report' => ['type' => 'string', 'enum' => array_keys(self::REPORTS), 'description' => 'Which report.'],
                'period' => ['type' => 'string', 'enum' => array_keys(ReportPeriod::PERIODS), 'description' => 'day, week, month or year.'],
                'date' => ['type' => 'string', 'description' => 'Any date inside the period, YYYY-MM-DD. Leave out for the current one.'],
            ],
            'required' => ['report', 'period'],
        ];
    }

    public function permissionTier(): string
    {
        return 'auto';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $report = $arguments['report'] ?? null;
        $period = $arguments['period'] ?? null;
        if (! isset(self::REPORTS[$report]) || ! isset(ReportPeriod::PERIODS[$period])) {
            return ToolResult::fail('report must be one of '.implode(', ', array_keys(self::REPORTS)).'; period one of '.implode(', ', array_keys(ReportPeriod::PERIODS)).'.');
        }

        $date = ! empty($arguments['date']) ? CarbonImmutable::createFromFormat('!Y-m-d', (string) $arguments['date'], config('app.timezone')) : null;
        $date = $date ?: CarbonImmutable::now(config('app.timezone'));
        [$route, $name] = self::REPORTS[$report];
        $label = $name.' · '.ReportPeriod::of($period, $date)->label();

        return ToolResult::ok(
            "Here's the {$label}. Tap Download in the chat to save it (CSV, opens in Excel).",
            ['download' => ['label' => $label, 'url' => route($route, ['period' => $period, 'date' => $date->toDateString()], false)]]
        );
    }
}
