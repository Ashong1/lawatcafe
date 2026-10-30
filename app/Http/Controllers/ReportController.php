<?php

namespace App\Http\Controllers;

use App\Services\SalesReportService;
use App\Services\StockReportService;
use App\Support\Csv;
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
            fputcsv($out, Csv::safe([ucfirst($period->bucket()), 'Net sales (PHP)']));
            foreach ($report['series'] as $key => $total) {
                fputcsv($out, Csv::safe([$labels[$key], number_format($total, 2, '.', '')]));
            }
            fputcsv($out, ['Total ('.$report['orders'].' orders)', number_format($report['net'], 2, '.', '')]);
            fclose($out);
        }, 'lawatkape-sales-'.$period->period.'-'.$period->start->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function stock(Request $request, StockReportService $reports)
    {
        $period = ReportPeriod::fromRequest($request);

        return view('reports.stock', ['period' => $period, 'report' => $reports->build($period)]);
    }

    public function stockCsv(Request $request, StockReportService $reports)
    {
        $period = ReportPeriod::fromRequest($request);
        $report = $reports->build($period);

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Ingredient', 'Unit', 'Start', 'Received', 'Used in sales', 'Wasted', 'Adjusted', 'End', 'Running low']);
            foreach ($report['rows'] as $r) {
                fputcsv($out, Csv::safe([$r['ingredient'], $r['unit'], $r['start'], $r['received'], $r['used'], $r['wasted'], $r['adjusted'], $r['end'], $r['low'] ? 'Yes' : 'No']));
            }
            fclose($out);
        }, 'lawatkape-stock-'.$period->period.'-'.$period->start->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
