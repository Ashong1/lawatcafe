<?php

namespace App\Http\Controllers;

use App\Models\AiFinding;
use App\Models\Ingredient;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\Voucher;
use App\Services\Agent\ChatStreamResponder;
use App\Services\Agent\ConversationHistoryService;
use App\Services\Agent\LessonLibrary;
use App\Services\Agent\ToolRegistry;
use App\Services\AIService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StaffController extends Controller
{
    public function index()
    {
        // Initial load passes initial data
        $activeShift = $this->getActiveShift();
        $eightySixList = $this->getEightySixList();
        $shiftNotes = $this->getShiftNotes();
        $pendingOrdersCount = $this->getPendingOrdersCount();
        $unusedVouchers = $this->getUnusedVouchersCount();
        $aiFindings = $this->getAiFindings();

        return view('staff.dashboard', compact(
            'activeShift',
            'eightySixList',
            'shiftNotes',
            'pendingOrdersCount',
            'unusedVouchers',
            'aiFindings'
        ));
    }

    public function getLiveData()
    {
        $activeShift = $this->getActiveShift();

        $eightySixList = $this->getEightySixList()->map(function ($item) {
            return [
                'name' => $item->name,
                'current_stock' => $item->current_stock,
                'unit' => $item->unit,
                'is_sold_out' => $item->current_stock <= 0,
            ];
        });

        $aiFindings = $this->getAiFindings()->map(fn ($f) => [
            'summary' => $f->summary,
            'severity' => $f->severity,
            'created_at' => $f->created_at->diffForHumans(),
        ]);

        return response()->json([
            'hasActiveShift' => (bool) $activeShift,
            'shift' => $activeShift ? [
                'started_at' => Carbon::parse($activeShift->started_at)->format('h:i A'),
                'duration' => Carbon::parse($activeShift->started_at)->diffForHumans(),
                'starting_cash' => number_format($activeShift->starting_cash, 2),
                'role' => auth()->user()->role,
            ] : null,
            'eightySixList' => $eightySixList,
            'shiftNotes' => $this->getShiftNotes(),
            'pendingOrdersCount' => $this->getPendingOrdersCount(),
            'unusedVouchers' => $this->getUnusedVouchersCount(),
            'aiFindings' => $aiFindings,
            'currentTime' => now()->format('l, F jS - h:i A'),
        ]);
    }

    private function getActiveShift(): ?Shift
    {
        return Shift::where('user_id', auth()->id())->where('status', 'open')->latest()->first();
    }

    private function getEightySixList(): Collection
    {
        $lowStockThreshold = (int) Setting::get('low_stock_threshold', 500);

        return Ingredient::where('current_stock', '<=', $lowStockThreshold)->get(['name', 'current_stock', 'unit']);
    }

    private function getShiftNotes(): string
    {
        return Setting::get('shift_notes', 'Welcome to your shift! No special announcements right now.');
    }

    private function getPendingOrdersCount(): int
    {
        return Sale::whereIn('status', ['pending', 'preparing'])->count();
    }

    private function getUnusedVouchersCount(): int
    {
        return Voucher::where('is_used', false)->count();
    }

    private function getAiFindings(): Collection
    {
        return AiFinding::where('audience', 'staff')->latest()->take(5)->get(['summary', 'severity', 'created_at']);
    }

    public function staffChat(Request $request, AIService $ai, ConversationHistoryService $conversations, ChatStreamResponder $responder)
    {
        // history.*.role restricted to user/assistant — see the matching
        // fix (and full reasoning) on CaptivePortalController::chat().
        $request->validate([
            'message' => 'required|string|max:1000',
            // A generous DoS backstop, not a conversation-length limit — see
            // the matching comment on DashboardController::adminChat().
            'history' => 'nullable|array|max:200',
            'history.*.role' => 'required_with:history|in:user,assistant',
            // nullable, not required_with: a tool-only turn with no reply text
            // can end up stored (or cached client-side from before that was
            // guarded) with content null — that's stale data to drop below,
            // not a malformed request worth 422ing the whole conversation over.
            'history.*.content' => 'nullable|string|max:4000',
            'conversation_id' => 'nullable|integer',
        ]);

        $conversation = $conversations->resolve($request->integer('conversation_id') ?: null, $request->user()->id, 'staff');

        // Worked examples are retrieved per message rather than baked into the
        // system prompt, because which past answer is relevant depends entirely
        // on what was just asked — see LessonLibrary::exemplarsFor(). Appended
        // to the system turn so it keeps the same trust level as the rest of the
        // approved guidance, rather than arriving as user-role text.
        $messages = [['role' => 'system', 'content' => $ai->buildStaffSystemPrompt($request->user()).app(LessonLibrary::class)->exemplarBlockFor('staff', $request->message)]];
        foreach ($conversations->slidingWindow($request->history ?? []) as $msg) {
            if (! empty($msg['content'])) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $request->message];

        return $responder->stream(
            $messages,
            ToolRegistry::AUDIENCE_STAFF,
            $request->user(),
            [],
            $request->message,
            '☕ Staff AI stack offline.',
            $conversation,
            $conversations,
        );
    }
}
