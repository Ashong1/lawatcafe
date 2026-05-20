<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SalesController extends Controller
{
    /**
     * Display the Sales Dashboard with charts and sparklines.
     */
    public function index()
    {
        $sales = Sale::with('user')->latest()->paginate(20);
        $totalRevenue = Sale::sum('total_amount');
        $todaysRevenue = Sale::whereDate('created_at', today())->sum('total_amount');

        // Main Weekly Graph Data
        $weeklySales = Sale::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->where('created_at', '>=', now()->subDays(6))
            ->groupBy('date')->orderBy('date', 'ASC')->get();
        
        $chartLabels = $weeklySales->pluck('date')->map(fn($date) => date('M d', strtotime($date)));
        $chartData = $weeklySales->pluck('total');

        // --- Sparkline Data for Cards ---

        // 1. Today's Hourly Trend (Green Chart)
        $hourlySales = Sale::selectRaw('HOUR(created_at) as hour, SUM(total_amount) as total')
            ->whereDate('created_at', today())
            ->groupBy('hour')->pluck('total', 'hour')->toArray();
        
        $todaySparkline = [];
        for ($i = 0; $i <= 23; $i++) {
            $todaySparkline[] = $hourlySales[$i] ?? 0; // Fill missing hours with 0
        }

        // 2. Lifetime Trend (Brown Chart - Grouped by Month)
        $lifetimeSparkline = Sale::selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, SUM(total_amount) as total')
            ->groupBy('month')->orderBy('month', 'ASC')
            ->pluck('total')->toArray();
        
        // Fallback if no sales exist to prevent chart errors
        if (empty($lifetimeSparkline)) $lifetimeSparkline = [0, 0, 0]; 

        return view('sales.index', compact(
            'sales', 'totalRevenue', 'todaysRevenue', 
            'chartLabels', 'chartData', 
            'todaySparkline', 'lifetimeSparkline'
        ));
    }

    /**
     * Export today's sales as a downloadable CSV file.
     */
    public function export()
    {
        // Get all sales from today
        $sales = Sale::whereDate('created_at', today())->get();
        
        // Name the file dynamically based on today's date
        $fileName = 'lawat_daily_sales_' . today()->format('Y_m_d') . '.csv';

        // Set the headers to force a file download
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        // Create the CSV content
        $callback = function() use($sales) {
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
                    $sale->created_at->format('h:i A') // Formats time nicely (e.g., 02:30 PM)
                ]);
            }
            
            // Close the stream
            fclose($file);
        };

        // Stream the download back to the browser
        return response()->stream($callback, 200, $headers);
    }
}