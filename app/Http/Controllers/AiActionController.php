<?php

namespace App\Http\Controllers;

use App\Models\AiActionAudit;
use App\Services\Agent\ToolCallOrchestrator;
use App\Support\AgentActivityEntry;
use Illuminate\Http\Request;

class AiActionController extends Controller
{
    /**
     * Written for the cafe owner, not an auditor (see AgentActivityEntry).
     * Anything waiting for approval comes first; routine look-ups — the AI
     * reading sales, sessions or stock, which changes nothing — are hidden
     * unless asked for, because they outnumbered real actions about 3 to 1.
     */
    public function index(Request $request)
    {
        $showRoutine = $request->query('show') === 'all';
        $routine = AgentActivityEntry::routineTools();

        $pending = AiActionAudit::pending()->with(['actor', 'approvedBy'])->latest()->get()
            ->map(fn ($a) => new AgentActivityEntry($a));

        $actions = AiActionAudit::with(['actor', 'approvedBy'])
            ->where('status', '!=', 'proposed')
            ->when(! $showRoutine, fn ($q) => $q->whereNotIn('tool_name', $routine)->where('tool_name', '!=', 'None'))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $hiddenRoutineCount = $showRoutine ? 0 : AiActionAudit::where('status', '!=', 'proposed')
            ->where(fn ($q) => $q->whereIn('tool_name', $routine)->orWhere('tool_name', 'None'))
            ->count();

        return view('admin.agent.activity', [
            'pending' => $pending,
            'actions' => $actions,
            'days' => $actions->getCollection()
                ->map(fn ($a) => new AgentActivityEntry($a))
                ->groupBy(fn (AgentActivityEntry $e) => $e->audit->created_at->isToday() ? 'Today'
                    : ($e->audit->created_at->isYesterday() ? 'Yesterday' : $e->audit->created_at->format('l, F j'))),
            'showRoutine' => $showRoutine,
            'hiddenRoutineCount' => $hiddenRoutineCount,
        ]);
    }

    /**
     * Admins see every pending action org-wide; staff see only their own
     * proposals (they have no page to triage anyone else's, and their role
     * floor means most of their mutating tool calls land here needing
     * confirmation — this is often their only path to get an action to run).
     */
    public function pendingCount(Request $request)
    {
        $query = AiActionAudit::pending();
        if (! $request->user()->isAdminOrAbove()) {
            $query->where('actor_user_id', $request->user()->id);
        }

        return response()->json(['count' => $query->count()]);
    }

    public function pendingPreview(Request $request)
    {
        $query = AiActionAudit::pending()->with('actor')->latest()->limit(8);
        if (! $request->user()->isAdminOrAbove()) {
            $query->where('actor_user_id', $request->user()->id);
        }

        return response()->json($query->get(['id', 'tool_name', 'actor_user_id', 'input_params', 'created_at'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'tool_name' => $a->tool_name,
                'label' => AgentActivityEntry::labelFor($a->tool_name, true),
                'actor' => $a->actor ? ['name' => $a->actor->name] : null,
                'input_params' => $a->input_params,
                'created_at' => $a->created_at->toIso8601String(),
            ]));
    }

    /**
     * Lets a chat widget re-sync the resolution of pending actions it rendered
     * inline, in case they were approved/rejected elsewhere (e.g. the Agent
     * Activity page) rather than through the chat's own confirm/reject buttons.
     * Same ownership scoping as pendingCount/pendingPreview: admins can check
     * any id, staff only their own — otherwise a guessed id would leak another
     * user's proposal outcome.
     */
    public function statuses(Request $request)
    {
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('ids'))));

        $query = AiActionAudit::with('approvedBy')->whereIn('id', $ids);
        if (! $request->user()->isAdminOrAbove()) {
            $query->where('actor_user_id', $request->user()->id);
        }

        return response()->json($query->get(['id', 'status', 'result', 'approved_by_user_id'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'status' => $a->status,
                'message' => $a->result['message'] ?? null,
                'approved_by' => $a->approvedBy?->name,
            ]));
    }

    public function confirm(Request $request, AiActionAudit $audit, ToolCallOrchestrator $orchestrator)
    {
        $result = $orchestrator->confirmPending($audit, $request->user());

        if ($request->wantsJson()) {
            return response()->json(['success' => $result->success, 'message' => $result->message], $result->success ? 200 : 422);
        }

        return redirect()->back()->with($result->success ? 'success' : 'error', $result->message);
    }

    public function reject(Request $request, AiActionAudit $audit, ToolCallOrchestrator $orchestrator)
    {
        $rejected = $orchestrator->rejectPending($audit, $request->user());
        $message = $rejected
            ? 'Proposed action rejected.'
            : 'Unable to reject this action — it may no longer be pending, or it belongs to someone else.';

        if ($request->wantsJson()) {
            return response()->json(['success' => $rejected, 'message' => $message], $rejected ? 200 : 422);
        }

        return redirect()->back()->with($rejected ? 'success' : 'error', $message);
    }
}
