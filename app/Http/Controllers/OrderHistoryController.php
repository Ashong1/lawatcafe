<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Models\Setting;
use App\Support\Csv;
use App\Support\ReportPeriod;
use App\Services\SaleService;
use Illuminate\Http\Request;

class OrderHistoryController extends Controller
{
    public function __construct(protected SaleService $sales) {}

    public function index(Request $request)
    {
        $sales = $this->filtered($request)->paginate(20)->withQueryString();

        $pendingVoidRequests = auth()->user()->isAdminOrAbove()
            ? SaleVoidRequest::with(['sale', 'requestedBy'])->where('status', 'pending')->latest()->get()
            : collect();

        return view('pos.history', [
            'sales' => $sales,
            'pendingVoidRequests' => $pendingVoidRequests,
            'receiptPrintingEnabled' => Setting::receiptPrintingEnabled(),
            'period' => ReportPeriod::fromRequest($request),
        ]);
    }

    /** The orders on screen (same filters) as a spreadsheet. */
    public function export(Request $request)
    {
        $period = ReportPeriod::fromRequest($request);
        $sales = $this->filtered($request)->lazy(500);   // a whole year of orders, a batch at a time

        return response()->streamDownload(function () use ($sales) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order no.', 'Date', 'Time', 'Type', 'Items', 'Payment', 'Discount', 'Total', 'Refunded', 'Status', 'Cashier']);
            foreach ($sales as $sale) {
                fputcsv($out, Csv::safe([
                    $sale->transaction_number, $sale->created_at->format('Y-m-d'), $sale->created_at->format('g:i A'),
                    $sale->order_type === 'takeaway' ? 'Take away' : 'Dine in',
                    $sale->items->map(fn ($i) => $i->quantity.'× '.$i->item_name)->implode(', '),
                    $sale->payment_method, number_format((float) $sale->discount_amount, 2, '.', ''),
                    number_format((float) $sale->total_amount, 2, '.', ''), number_format((float) $sale->refunded_amount, 2, '.', ''),
                    $sale->status === 'cancelled' ? 'Voided' : ucfirst($sale->status), $sale->user?->name,
                ]));
            }
            fclose($out);
        }, 'lawatkape-orders-'.$period->period.'-'.$period->start->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Orders for the chosen day/week/month/year (default today), order number and status. */
    private function filtered(Request $request)
    {
        $period = ReportPeriod::fromRequest($request);

        return Sale::with(['items', 'user'])->latest()
            ->whereBetween('created_at', [$period->start, $period->end])
            ->when($request->filled('search'), fn ($q) => $q->where('transaction_number', 'like', '%'.$request->search.'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status));
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

    public function refund(Sale $sale, Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*' => 'integer|min:0',
            'reason' => 'required|string|max:500',
        ]);

        $result = $this->sales->refund($sale, $validated['items'], $validated['reason'], auth()->user());

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
