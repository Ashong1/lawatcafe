<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\Request;

class OrderHistoryController extends Controller
{
    public function __construct(protected SaleService $sales)
    {
    }

    public function index(Request $request)
    {
        $query = Sale::with(['items', 'user'])->latest();

        // Search by Transaction Number
        if ($request->filled('search')) {
            $query->where('transaction_number', 'like', '%' . $request->search . '%');
        }

        // Filter by Date
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        } else {
            $query->whereDate('created_at', now()->toDateString());
        }

        // Filter by Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sales = $query->paginate(20);

        return view('pos.history', compact('sales'));
    }

    public function void(Sale $sale)
    {
        $result = $this->sales->void($sale, auth()->id());

        return redirect()->back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
