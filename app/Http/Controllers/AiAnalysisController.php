<?php

namespace App\Http\Controllers;

use App\Models\AiAnalysisRun;

class AiAnalysisController extends Controller
{
    /**
     * Browsable history of agent:analyze runs, including findings older than
     * the dashboard's "latest few".
     */
    public function index()
    {
        $audience = auth()->user()->isAdminOrAbove() ? null : 'staff';

        $runs = AiAnalysisRun::query()
            ->when($audience, fn ($q) => $q->whereHas('findings', fn ($f) => $f->where('audience', $audience)))
            ->with(['findings' => fn ($q) => $audience ? $q->where('audience', $audience) : $q])
            ->latest()
            ->paginate(15);

        return view('ai.analysis-history', compact('runs'));
    }
}
