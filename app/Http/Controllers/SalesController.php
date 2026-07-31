<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Support\Facades\Cache;

class SalesController extends Controller
{
    /**
     * Display the Sales Dashboard with charts and sparklines.
     */
    public function index()
    {
        $sales = Sale::with('user')->latest()->paginate(20);

        // The paginated list above is a single indexed query per page and stays
        // live; everything below is a repeated full-table aggregate (5 queries
        // scanning/grouping the whole sales table) that looks the same for
        // every visitor within the same minute, so it's cached like
        // DashboardController's stats/charts blocks.
        $aggregates = Cache::remember('sales_dashboard_aggregates', 60, function () {
            // HOUR()/DATE_FORMAT() are MySQL-only — this app runs MySQL in prod,
            // but the test suite uses SQLite, which needs strftime() instead.
            // Branching on the driver keeps one query for both instead of
            // silently only working against one database engine.
            $driver = Sale::query()->getConnection()->getDriverName();
            $hourExpr = $driver === 'sqlite'
                ? "CAST(strftime('%H', created_at) AS INTEGER)"
                : 'HOUR(created_at)';
            $monthExpr = $driver === 'sqlite'
                ? "strftime('%Y-%m', created_at)"
                : 'DATE_FORMAT(created_at, "%Y-%m")';

            $totalRevenue = Sale::sum('total_amount');
            $todaysRevenue = Sale::whereDate('created_at', today())->sum('total_amount');

            // Main Weekly Graph Data
            $weeklySales = Sale::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
                ->where('created_at', '>=', now()->subDays(6))
                ->groupBy('date')->orderBy('date', 'ASC')->get();

            $chartLabels = $weeklySales->pluck('date')->map(fn ($date) => date('M d', strtotime($date)));
            $chartData = $weeklySales->pluck('total');

            // --- Sparkline Data for Cards ---

            // 1. Today's Hourly Trend (Green Chart)
            $hourlySales = Sale::selectRaw("{$hourExpr} as hour, SUM(total_amount) as total")
                ->whereDate('created_at', today())
                ->groupBy('hour')->pluck('total', 'hour')->toArray();

            $todaySparkline = [];
            for ($i = 0; $i <= 23; $i++) {
                $todaySparkline[] = $hourlySales[$i] ?? 0; // Fill missing hours with 0
            }

            // 2. Lifetime Trend (Brown Chart - Grouped by Month)
            $lifetimeSparkline = Sale::selectRaw("{$monthExpr} as month, SUM(total_amount) as total")
                ->groupBy('month')->orderBy('month', 'ASC')
                ->pluck('total')->toArray();

            // Fallback if no sales exist to prevent chart errors
            if (empty($lifetimeSparkline)) {
                $lifetimeSparkline = [0, 0, 0];
            }

            return compact(
                'totalRevenue', 'todaysRevenue',
                'chartLabels', 'chartData',
                'todaySparkline', 'lifetimeSparkline'
            );
        });

        return view('sales.index', array_merge(['sales' => $sales], $aggregates));
    }

    /**
     * Export today's sales as a downloadable CSV file.
     */
    public function export()
    {
        // Get all sales from today
        $sales = Sale::whereDate('created_at', today())->get();

        // Name the file dynamically based on today's date
        $fileName = 'lawat_daily_sales_'.today()->format('Y_m_d').'.csv';

        // Set the headers to force a file download
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$fileName",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        // Create the CSV content
        $callback = function () use ($sales) {
            // Open output stream
            $file = fopen('php://output', 'w');

            // Add the CSV Header Row
            fputcsv($file, ['Transaction Number', 'Total Amount', 'Payment Method', 'Time of Sale']);

            // Loop through each sale and add it as a row
            foreach ($sales as $sale) {
                fputcsv($file, [
                    $sale->transaction_number,
                    $sale->total_amount,
                    $sale->payment_method,
                    $sale->created_at->format('h:i A'), // Formats time nicely (e.g., 02:30 PM)
                ]);
            }

            // Close the stream
            fclose($file);
        };

        // Stream the download back to the browser
        return response()->stream($callback, 200, $headers);
    }
}
