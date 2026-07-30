<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use Illuminate\Http\Request;

/**
 * History for the admin/staff Barista AI widgets (see agent-chat.blade.php's
 * historyEnabled prop). Every action here is scoped to the authenticated
 * user's own rows — there is no cross-user browsing, by design.
 */
class AiConversationController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'context' => 'required|string|in:admin,staff',
        ]);

        $conversations = AiConversation::where('user_id', $request->user()->id)
            ->where('context', $validated['context'])
            ->whereNotNull('last_message_at')
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get(['id', 'title', 'last_message_at']);

        return response()->json($conversations);
    }

    public function show(Request $request, AiConversation $conversation)
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        return response()->json(['messages' => $conversation->messages]);
    }

    public function destroy(Request $request, AiConversation $conversation)
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        $conversation->delete();

        return response()->json(['success' => true]);
    }
}
