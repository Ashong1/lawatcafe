<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'event' => ['nullable', Rule::in(array_keys(ActivityLog::LABELS))],
            'user' => ['nullable', 'integer'],
        ]);

        $logs = ActivityLog::with('user:id,name,role')
            ->when($filters['event'] ?? null, fn ($q, $e) => $q->where('event', $e))
            ->when($filters['user'] ?? null, fn ($q, $u) => $q->where('user_id', $u))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.activity-log', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'failedToday' => ActivityLog::where('event', 'sign_in_failed')->where('created_at', '>=', today())->count(),
            'lastBackup' => ActivityLog::where('event', 'backup_made')->latest('id')->first(),
        ]);
    }
}
