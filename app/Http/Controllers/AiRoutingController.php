<?php

namespace App\Http\Controllers;

use App\Services\AIService;
use App\Services\AiRouteLog;

/** System Administration → AI Routing: the orchestration, live (owner, Oct 30). */
class AiRoutingController extends Controller
{
    public function index()
    {
        return view('admin.ai.routing');
    }

    public function feed(AIService $ai, AiRouteLog $log)
    {
        $stats = $log->stats();

        return response()->json([
            'providers' => collect($ai->getProviderStatuses())->map(fn ($p, $key) => [
                'key' => $key,
                'label' => $p['label'],
                'configured' => $p['configured'],
                'resting' => $p['circuit']['open'],
                'models' => count($p['models']),
                'answered' => $stats[$key]['answered'] ?? 0,
                'failed' => $stats[$key]['failed'] ?? 0,
                'avg_ms' => $stats[$key]['avg_ms'] ?? null,
            ])->values(),
            'requests' => $log->requests(),
        ]);
    }
}
