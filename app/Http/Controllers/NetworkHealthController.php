<?php

namespace App\Http\Controllers;

use App\Models\NetworkHealthCheck;
use App\Services\NetworkHealthService;

/** Network > Health: every network check on one screen, with 24 hours of history. */
class NetworkHealthController extends Controller
{
    public function index(NetworkHealthService $health)
    {
        $latest = $health->latest();
        if (! $latest) {
            $latest = $health->run();
            $health->record($latest);
        }

        // One point per 5 minutes from the per-minute history keeps the charts readable.
        $history = NetworkHealthCheck::where('checked_at', '>=', now()->subDay())
            ->orderBy('checked_at')
            ->get(['checked_at', 'internet_latency_ms', 'internet_loss_pct', 'guests_online', 'dhcp_used', 'dhcp_size'])
            ->filter(fn ($row, $i) => $i % 5 === 0)
            ->values();

        return view('network.health', [
            'latest' => $latest,
            'history' => [
                'labels' => $history->map(fn ($r) => $r->checked_at->format('g:i A'))->all(),
                'latency' => $history->pluck('internet_latency_ms')->all(),
                'loss' => $history->pluck('internet_loss_pct')->all(),
                'guests' => $history->pluck('guests_online')->all(),
            ],
        ]);
    }

    public function run(NetworkHealthService $health)
    {
        $result = $health->run();
        $health->record($result);

        return redirect()->route('network.health')->with('success', 'Network checked just now.');
    }

    /** Lets the open page notice a newer automatic check and refresh itself. */
    public function status(NetworkHealthService $health)
    {
        return response()->json(['checked_at' => $health->latest()['checked_at'] ?? null]);
    }
}
