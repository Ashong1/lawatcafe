<?php

namespace App\Http\Controllers;

use App\Models\AiFinding;
use App\Models\Ingredient;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\Voucher;
use App\Services\Agent\ChatImage;
use App\Services\Agent\ChatStreamResponder;
use App\Services\Agent\ConversationHistoryService;
use App\Services\Agent\LessonLibrary;
use App\Services\Agent\ToolRegistry;
use App\Services\AIService;
use App\Services\GuestSessionService;
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
        // Not fetched here: it's a firewall round-trip, and the page must
        // never wait on OPNsense to paint. The first live poll (fired
        // immediately on load) fills it in.
        $guestsOnline = null;

        return view('staff.dashboard', compact(
            'activeShift',
            'eightySixList',
            'shiftNotes',
            'pendingOrdersCount',
            'unusedVouchers',
            'aiFindings',
            'guestsOnline'
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
            'guestsOnline' => $this->getGuestsOnline(),
            'aiFindings' => $aiFindings,
            'currentTime' => now()->format('l, F jS - h:i A'),
        ]);
    }

    /**
     * Paying guests on the Wi-Fi, by the same definition the admin dashboard
     * uses (GuestSessionService). Null when the firewall can't be reached —
     * the card shows a dash rather than the dashboard failing to load.
     */
    private function getGuestsOnline(): ?int
    {
        try {
            return app(GuestSessionService::class)->activeGuestCount();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function getActiveShift(): ?Shift
    {
        return Shift::where('user_id', auth()->id())->where('status', 'open')->latest()->first();
    }

    private function getEightySixList(): Collection
    {
        // Each ingredient's own threshold — see the note in
        // DashboardController::getStats() for why the shop-wide number went.
        return Ingredient::whereColumn('current_stock', '<=', 'low_stock_threshold')
            ->get(['name', 'current_stock', 'unit']);
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
            // Text, a photo, or both — see ChatImage.
            ...ChatImage::rules(),
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

        $text = (string) $request->input('message', '');
        $image = ChatImage::fromRequest($request);

        $conversation = $conversations->resolve($request->integer('conversation_id') ?: null, $request->user()->id, 'staff');

        // Worked examples are retrieved per message rather than baked into the
        // system prompt, because which past answer is relevant depends entirely
        // on what was just asked — see LessonLibrary::exemplarsFor(). Appended
        // to the system turn so it keeps the same trust level as the rest of the
        // approved guidance, rather than arriving as user-role text.
        $messages = [['role' => 'system', 'content' => $ai->buildStaffSystemPrompt($request->user()).app(LessonLibrary::class)->exemplarBlockFor('staff', $text).ChatImage::systemNote($image)]];
        foreach ($conversations->slidingWindow($request->history ?? []) as $msg) {
            if (! empty($msg['content'])) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => ChatImage::userContent($text, $image)];

        return $responder->stream(
            $messages,
            ToolRegistry::AUDIENCE_STAFF,
            $request->user(),
            [],
            ChatImage::historyText($text, $image),
            '☕ Staff AI stack offline.',
            $conversation,
            $conversations,
        );
    }
}
