<?php

namespace App\Http\Controllers;

use App\Services\SalesReportService;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function sales(Request $request, SalesReportService $reports)
    {
        $period = ReportPeriod::fromRequest($request);

        return view('reports.sales', ['period' => $period, 'report' => $reports->build($period)]);
    }

    public function salesCsv(Request $request, SalesReportService $reports)
    {
        $period = ReportPeriod::fromRequest($request);
        $report = $reports->build($period);
        $labels = $period->buckets();

        return response()->streamDownload(function () use ($report, $labels, $period) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [ucfirst($period->bucket()), 'Net sales (PHP)']);
            foreach ($report['series'] as $key => $total) {
                fputcsv($out, [$labels[$key], number_format($total, 2, '.', '')]);
            }
            fputcsv($out, ['Total ('.$report['orders'].' orders)', number_format($report['net'], 2, '.', '')]);
            fclose($out);
        }, 'lawatkape-sales-'.$period->period.'-'.$period->start->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
