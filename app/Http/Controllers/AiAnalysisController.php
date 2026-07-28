<?php

namespace App\Http\Controllers;

use App\Models\AiAnalysisRun;

class AiAnalysisController extends Controller
{
    /**
     * Browsable history of proactive agent:analyze runs — the narrative and
     * older findings previously only existed in the DB with no view, once
     * the "latest few" dashboard widget scrolled past them.
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
