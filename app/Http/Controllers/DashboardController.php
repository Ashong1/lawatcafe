<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Voucher;
use App\Models\Product;
use App\Models\Ingredient;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // 1. Basic Stats for the Top Cards
        $availableVouchers = Voucher::where('is_used', false)->count();
        $todaysSales = Sale::whereDate('created_at', Carbon::today())->sum('total_amount');
        $lowStockCount = Ingredient::where('current_stock', '<', 500)->count(); // Adjust threshold as needed
        
        // --- 2. CHART DATA: Weekly Sales Trend (Line Chart) ---
        // Get sales for the last 7 days grouped by date
        $salesData = Sale::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->where('created_at', '>=', Carbon::now()->subDays(6))
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get()
            ->pluck('total', 'date')
            ->toArray();

        // Fill in missing days with 0 so the chart always shows 7 days
        $chartLabels = [];
        $chartValues = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $chartLabels[] = Carbon::parse($date)->format('M d'); // e.g., "May 07"
            $chartValues[] = $salesData[$date] ?? 0;
        }

        // --- 3. CHART DATA: Category Distribution (Doughnut Chart) ---
        // For demonstration, we'll group by category. If you don't have sales linked to categories yet, 
        // you can count products per category instead.
        $categoryData = Product::selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();
            
        // Fallback if empty
        if (empty($categoryData)) {
            $categoryData = ['Coffee' => 10, 'Pastries' => 5, 'Meals' => 2];
        }

        return view('dashboard', compact(
            'availableVouchers', 'todaysSales', 'lowStockCount',
            'chartLabels', 'chartValues', 'categoryData'
        ));
    }
}