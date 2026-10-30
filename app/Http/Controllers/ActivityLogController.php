<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'event' => ['nullable', Rule::in(array_keys(ActivityLog::LABELS))],
            'user' => ['nullable', 'integer'],
            'category' => ['nullable', Rule::in(array_keys(ActivityLog::CATEGORIES))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'where' => ['nullable', Rule::in(['remote', 'shop'])],
        ]);

        $logs = ActivityLog::with('user:id,name,role')
            ->when($filters['event'] ?? null, fn ($q, $e) => $q->where('event', $e))
            ->when($filters['user'] ?? null, fn ($q, $u) => $q->where('user_id', $u))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->whereIn('event', ActivityLog::CATEGORIES[$c]))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $visits = SiteVisit::with('user:id,name,role')
            ->when($filters['where'] ?? null, fn ($q, $w) => $q->where('remote', $w === 'remote'))
            ->latest('id')
            ->paginate(25, ['*'], 'visits_page')
            ->withQueryString();

        return view('admin.security', [
            'visits' => $visits,
            'remoteVisitsThisWeek' => SiteVisit::where('remote', true)->where('created_at', '>=', now()->subDays(7))->count(),
            'backups' => app(BackupService::class)->list(),
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'failedToday' => ActivityLog::where('event', 'sign_in_failed')->where('created_at', '>=', today())->count(),
            'remote' => app(\App\Services\RemoteAccessService::class),
            'lastBackup' => app(BackupService::class)->list()->first()['made_at'] ?? null,
        ]);
    }
}
