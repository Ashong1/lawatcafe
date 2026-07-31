<?php

namespace App\Http\Controllers;

use App\Models\AiActionAudit;
use App\Services\Agent\ToolCallOrchestrator;
use Illuminate\Http\Request;

class AiActionController extends Controller
{
    public function index()
    {
        $actions = AiActionAudit::with(['actor', 'approvedBy'])->latest()->paginate(30);

        return view('admin.agent.activity', compact('actions'));
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
