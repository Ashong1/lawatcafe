<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Services\SaleService;
use Illuminate\Http\Request;

class OrderHistoryController extends Controller
{
    public function __construct(protected SaleService $sales) {}

    public function index(Request $request)
    {
        $query = Sale::with(['items', 'user'])->latest();

        // Search by Transaction Number
        if ($request->filled('search')) {
            $query->where('transaction_number', 'like', '%'.$request->search.'%');
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

        $pendingVoidRequests = auth()->user()->isAdminOrAbove()
            ? SaleVoidRequest::with(['sale', 'requestedBy'])->where('status', 'pending')->latest()->get()
            : collect();

        return view('pos.history', compact('sales', 'pendingVoidRequests'));
    }

    public function void(Sale $sale, Request $request)
    {
        if (auth()->user()->isAdminOrAbove()) {
            $result = $this->sales->void($sale, auth()->id());

            return redirect()->back()->with($result['success'] ? 'success' : 'error', $result['message']);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $result = $this->sales->requestVoid($sale, auth()->user(), $validated['reason']);

        return redirect()->back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function approveVoidRequest(SaleVoidRequest $void_request)
    {
        $result = $this->sales->approveVoidRequest($void_request, auth()->user());

        return redirect()->route('pos.history')->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function rejectVoidRequest(SaleVoidRequest $void_request)
    {
        $result = $this->sales->rejectVoidRequest($void_request, auth()->user());

        return redirect()->route('pos.history')->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
