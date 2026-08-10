@props([
    'endpoint',
    'title' => 'Barista AI',
    'subtitle' => '',
    'greeting' => "Hello! I'm Barista AI. How can I help?",
    'anchorId' => 'agent',
    'mode' => 'floating',
    'csrf' => true,
    'rateLimitMessage' => "I'm a bit busy right now — please try again in a moment.",
    // Server-side, per-account conversation history (list past conversations,
    // resume one, start a new one) — only meaningful for authenticated
    // widgets (admin/staff). anchorId doubles as the backend "context" value
    // ('admin'/'staff') when this is on. Guest portal chat never sets this:
    // those kiosks are often shared between different customers with no
    // durable account to key history off.
    'historyEnabled' => false,
])

@php
    // Which chat surface this is, in the learning loop's vocabulary. Derived
    // from anchorId ('portal' is the guest one) so no existing call site has to
    // pass anything new.
    $audience = match ($anchorId) {
        'portal' => 'guest',
        'staff' => 'staff',
        default => 'admin',
    };
@endphp

@if($mode === 'embedded')
    {{-- Embedded mode: no header/toggle/drag chrome — fills its parent's existing container. --}}
    <div x-data="agentChat({
            endpoint: @js($endpoint),
            greeting: @js($greeting),
            csrf: @js($csrf),
            csrfToken: @js(csrf_token()),
            rateLimitMessage: @js($rateLimitMessage),
            anchorId: @js($anchorId),
            historyEnabled: @js($historyEnabled),
            audience: @js($audience),
            feedbackEndpoint: @js(route('ai.feedback.store')),
        })"
        class="flex-1 min-h-0 flex flex-col"
        @open-agent-chat.window="handleExternalOpen($event.detail.prompt)"
        @portal-tab-changed.window="scrollToBottom()">

        {{-- flex-1 min-h-0 is what gives this box a height smaller than its
             content; max-h-full was removed because a percentage max-height
             against a flex-sized parent is unreliable and it added nothing.

             The scrollbar is deliberately visible (no `no-scrollbar` here):
             hiding it on a conversation that grows past the box leaves a guest
             with no cue that there is anything above, which is exactly how this
             was reported — "the chat is not scrollable". overscroll-contain
             stops a flick at the top of the history from dragging the whole
             portal panel instead. --}}
        <div class="overflow-y-auto overscroll-contain space-y-3 pr-1 w-full flex flex-col justify-start z-10 flex-1 min-h-0" id="{{ $anchorId }}-chat-history">
            <template x-for="(msg, index) in history" :key="index">
                <div class="anim-pop-in">
                    <template x-if="msg.kind === 'text'">
                        <div class="p-3 rounded-2xl shadow-sm text-xs font-medium relative w-fit max-w-[85%] break-words whitespace-normal mx-1"
                             :class="msg.role === 'user' ? 'bg-[#3E2723] text-white self-end rounded-br-sm' : 'bg-white text-[#4A3B32] border border-[#F0E6D2] self-start rounded-bl-sm'">
                            <span x-html="formatMarkdown(msg.content)" class="leading-relaxed"></span>
                            {{-- The reply is still being written. Without this
                                 there was no signal at all once the dots went
                                 away, so any pause mid-answer looked like a
                                 hang. No x-cloak: it lives inside an x-for
                                 template, which nothing renders before Alpine
                                 has booted and evaluated the condition. --}}
                            <span x-show="msg.streaming" aria-hidden="true"
                                  class="inline-block w-1.5 h-3 ml-0.5 -mb-0.5 bg-amber-500 rounded-sm animate-pulse"></span>
                        </div>
                    </template>

                    @include('components.partials.agent-chat-rating')

                    <template x-if="msg.kind === 'executed'">
                        <div class="flex items-start gap-2 max-w-[90%] p-3 rounded-xl text-[11px] font-bold bg-emerald-50 border border-emerald-200 text-emerald-800 mx-1">
                            <x-lucide-check-circle-2 class="w-4 h-4 shrink-0 mt-0.5" />
                            <span><span class="font-mono" x-text="msg.tool"></span>: <span x-text="msg.message"></span></span>
                        </div>
                    </template>

                    <template x-if="msg.kind === 'pending'">
                        <div class="max-w-[90%] p-4 rounded-xl bg-amber-50 border border-amber-300 space-y-2 mx-1">
                            <div class="flex items-center gap-2 text-[11px] font-black text-amber-800 uppercase tracking-tighter">
                                <x-lucide-clock class="w-4 h-4 shrink-0" />
                                <span x-text="msg.tool"></span>
                            </div>
                            <p class="text-[11px] text-amber-700 font-medium" x-text="formatArgs(msg.arguments)"></p>
                        </div>
                    </template>
                </div>
            </template>
            <div x-show="thinking" class="anim-pop-in bg-white p-3 rounded-2xl rounded-bl-sm shadow-sm border border-[#F0E6D2] self-start w-fit mx-1 flex items-center gap-2">
                <div class="flex gap-1">
                    <div class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-bounce"></div>
                    <div class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-bounce [animation-delay:0.2s]"></div>
                    <div class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-bounce [animation-delay:0.4s]"></div>
                </div>
                <span x-show="toolStatusLabel" x-text="toolStatusLabel" class="text-[10px] font-bold text-amber-700"></span>
            </div>
            <div id="{{ $anchorId }}-chat-anchor" class="h-1 w-full"></div>
        </div>

        <div class="flex gap-2 shrink-0 pt-3">
            <input type="text" x-model="message" @keydown.enter="send()" placeholder="Ask something..."
                   class="flex-1 bg-white border-2 border-[#F0E6D2] rounded-2xl px-4 py-3 text-xs font-bold focus:outline-none focus:border-[#3E2723] transition-all shadow-sm text-[#3E2723] placeholder:font-medium"
                   :disabled="streaming">
            <button @click="send()" class="bg-[#3E2723] text-white px-4 py-3 rounded-2xl hover:bg-[#271815] transition shadow-lg active:scale-95 disabled:opacity-50 flex items-center justify-center shrink-0" :disabled="streaming || !message.trim()">
                <x-lucide-send class="w-5 h-5" />
            </button>
        </div>
    </div>
@else
<div x-data="agentChat({
        endpoint: @js($endpoint),
        greeting: @js($greeting),
        csrf: @js($csrf),
        csrfToken: @js(csrf_token()),
        rateLimitMessage: @js($rateLimitMessage),
        anchorId: @js($anchorId),
        historyEnabled: @js($historyEnabled),
        audience: @js($audience),
        feedbackEndpoint: @js(route('ai.feedback.store')),
    })"
     class="fixed flex flex-col"
     :style="`left: ${posX}px; top: ${posY}px; width: ${chatWidth()}px; position: fixed !important; z-index: 9999 !important; bottom: auto !important; right: auto !important; transition: ${isDragging ? 'none' : 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)'}; touch-action: none;`"
     @pointermove.window="onDrag($event)"
     @pointerup.window="stopDrag()"
     @pointercancel.window="stopDrag()"
     @open-agent-chat.window="handleExternalOpen($event.detail.prompt)">

    {{-- Chat Window.

         Absolutely positioned above the toggle button rather than stacked in
         flow above it, which is what made closing look broken. In flow, the
         panel's ~500px of height sat between the widget's fixed `top` and the
         button, so the button's screen position depended on whether the panel
         was open — and toggle() compensated by shifting posY by exactly that
         amount. But posY animates (the root carries transition: all 0.3s) while
         display:none lands instantly at the end of the leave transition, so on
         close the button swooped a full panel-height down over 300ms and then
         snapped back up. Out of flow, the button simply never moves and the
         compensation is gone entirely. --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-90 translate-y-10"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-90 translate-y-10"
         class="absolute bottom-full left-0 mb-4 w-full origin-bottom bg-white rounded-[2rem] shadow-2xl border border-[#F0E6D2] overflow-hidden flex flex-col shadow-amber-900/10"
         :style="`height: ${chatHeight()}px`"
         style="display: none;">

        <!-- Header (Draggable Handle) -->
        <div class="bg-[#3E2723] p-6 text-white flex items-center justify-between cursor-move select-none"
             @pointerdown="startDrag($event)">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-amber-500 rounded-2xl flex items-center justify-center shadow-lg">
                    <x-lucide-bot class="w-6 h-6 text-[#3E2723]" />
                </div>
                <div>
                    <h3 class="text-sm font-black uppercase tracking-widest">{{ $title }}</h3>
                    @if($subtitle)
                        <p class="text-[9px] font-bold text-amber-200 uppercase tracking-tighter">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>
            <div class="flex items-center gap-1" x-show="historyEnabled">
                <button @click.stop="toggleHistory()" title="Past conversations" aria-label="Past conversations" class="text-amber-200 hover:text-white transition p-1.5 rounded-lg hover:bg-white/10">
                    <x-lucide-history class="w-5 h-5" />
                </button>
                <button @click.stop="newConversation()" title="New conversation" aria-label="New conversation" class="text-amber-200 hover:text-white transition p-1.5 rounded-lg hover:bg-white/10">
                    <x-lucide-square-pen class="w-5 h-5" />
                </button>
            </div>
            <button @click="open = false; clampPosition()" aria-label="Close" class="text-amber-200 hover:text-white transition shrink-0">
                <x-lucide-x class="w-6 h-6" />
            </button>
        </div>

        <!-- History Panel -->
        <div x-show="historyEnabled && showHistory"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             @click.outside="showHistory = false"
             class="absolute top-[92px] left-4 right-4 z-20 bg-white rounded-2xl shadow-2xl border border-[#F0E6D2] max-h-72 overflow-y-auto"
             style="display: none;">
            {{-- Rows rather than the word "Loading…": the list that replaces
                 this is a stack of conversation rows, so the panel keeps its
                 height instead of jumping when they arrive. --}}
            <div x-show="loadingHistory" class="divide-y divide-[#F0E6D2]">
                @for ($i = 0; $i < 3; $i++)
                    <div class="px-4 py-3 flex items-center justify-between gap-2">
                        <x-skeleton variant="text" :lines="1" class="flex-1 min-w-0" />
                        <x-skeleton variant="block" size="h-3" class="w-10 shrink-0" />
                    </div>
                @endfor
            </div>
            <div x-show="!loadingHistory && conversationList.length === 0" class="p-4 text-center text-[10px] font-black uppercase tracking-widest text-[#6D4C41]">No past conversations yet.</div>
            <template x-for="conv in loadingHistory ? [] : conversationList" :key="conv.id">
                <div @click="openConversation(conv.id)"
                     tabindex="0" role="button" @keydown.enter="openConversation(conv.id)" @keydown.space.prevent="openConversation(conv.id)"
                     class="flex items-center justify-between gap-2 px-4 py-3 border-b border-[#F0E6D2] last:border-0 cursor-pointer hover:bg-[#FDF8F5] transition group"
                     :class="conv.id === conversationId ? 'bg-amber-50' : ''">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-[#3E2723] truncate" x-text="conv.title || 'Conversation'"></p>
                        <p class="text-[9px] font-black uppercase tracking-widest text-[#6D4C41] mt-0.5" x-text="formatRelativeTime(conv.last_message_at)"></p>
                    </div>
                    <button @click.stop="deleteConversation(conv.id)" title="Delete" aria-label="Delete conversation" class="shrink-0 opacity-0 group-hover:opacity-100 text-[#D7CCC8] hover:text-red-600 transition p-1">
                        <x-lucide-trash-2 class="w-3.5 h-3.5" />
                    </button>
                </div>
            </template>
        </div>

        <!-- Messages Area -->
        {{-- Same min-h-0 / overscroll-contain reasoning as the embedded variant
             above: without min-h-0 a flex item will not shrink below its content,
             and without overscroll-contain a flick at the top of the history
             scrolls the page behind the widget instead. --}}
        <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain p-6 space-y-4 bg-[#FDF8F5]" id="{{ $anchorId }}-chat-history">
            <template x-for="(msg, index) in history" :key="index">
                <div class="anim-pop-in">
                    <!-- Plain text turn -->
                    <template x-if="msg.kind === 'text'">
                        <div class="flex flex-col" :class="msg.role === 'user' ? 'items-end' : 'items-start'">
                            <div class="max-w-[85%] p-4 rounded-2xl text-xs font-medium leading-relaxed shadow-sm"
                                 :class="msg.role === 'user' ? 'bg-[#3E2723] text-white rounded-tr-none' : 'bg-white text-[#4A3B32] border border-[#F0E6D2] rounded-tl-none'">
                                <span x-html="formatMarkdown(msg.content)"></span>
                                {{-- Still-writing caret — see the note on the
                                     same element in the floating variant. --}}
                                <span x-show="msg.streaming" aria-hidden="true"
                                      class="inline-block w-1.5 h-3 ml-0.5 -mb-0.5 bg-amber-500 rounded-sm animate-pulse"></span>
                            </div>
                            <span class="text-[8px] font-black uppercase tracking-widest text-[#6D4C41] mt-1.5 mx-1" x-text="msg.role === 'user' ? 'You' : @js($title)"></span>
                        </div>
                    </template>

                    @include('components.partials.agent-chat-rating')

                    <!-- Executed tool action -->
                    <template x-if="msg.kind === 'executed'">
                        <div class="flex items-start gap-2 max-w-[90%] p-3 rounded-xl text-[11px] font-bold bg-emerald-50 border border-emerald-200 text-emerald-800">
                            <x-lucide-check-circle-2 class="w-4 h-4 shrink-0 mt-0.5" />
                            <span><span class="font-mono" x-text="msg.tool"></span>: <span x-text="msg.message"></span></span>
                        </div>
                    </template>

                    <!-- Pending confirmation -->
                    <template x-if="msg.kind === 'pending'">
                        <div class="max-w-[90%] p-4 rounded-xl bg-amber-50 border border-amber-300 space-y-2">
                            <div class="flex items-center gap-2 text-[11px] font-black text-amber-800 uppercase tracking-tighter">
                                <x-lucide-clock class="w-4 h-4 shrink-0" />
                                <span x-text="msg.tool"></span>
                                <span class="text-[9px] font-bold text-amber-600">needs confirmation</span>
                            </div>
                            <p class="text-[11px] text-amber-700 font-medium" x-text="formatArgs(msg.arguments)"></p>

                            <template x-if="!msg.resolved">
                                <div class="flex gap-2 pt-1">
                                    <button @click="confirmAction(msg)" :disabled="resolvingId === msg.audit_id"
                                            class="flex-1 flex items-center justify-center gap-1 bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-black uppercase tracking-widest px-3 py-2 rounded-lg transition disabled:opacity-50">
                                        <template x-if="resolvingId === msg.audit_id"><x-lucide-loader-2 class="w-3 h-3 animate-spin" /></template>
                                        <span>Approve</span>
                                    </button>
                                    <button @click="rejectAction(msg)" :disabled="resolvingId === msg.audit_id"
                                            class="flex-1 bg-white hover:bg-red-50 border border-amber-300 text-amber-800 hover:text-red-700 text-[10px] font-black uppercase tracking-widest px-3 py-2 rounded-lg transition disabled:opacity-50">
                                        Reject
                                    </button>
                                </div>
                            </template>
                            <template x-if="msg.resolved">
                                <p class="text-[10px] font-black uppercase tracking-widest"
                                   :class="msg.resolution === 'approved' ? 'text-emerald-700' : 'text-red-600'"
                                   x-text="msg.resolution === 'approved' ? '✓ Approved and executed' : (msg.resolution === 'rejected' ? '✗ Rejected' : '✗ Failed: ' + (msg.resolutionMessage || ''))"></p>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            <div x-show="thinking" class="anim-pop-in flex flex-col items-start">
                <div class="bg-white border border-[#F0E6D2] p-4 rounded-2xl rounded-tl-none shadow-sm flex items-center gap-2">
                    <div class="flex gap-1">
                        <div class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-bounce"></div>
                        <div class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-bounce [animation-delay:0.2s]"></div>
                        <div class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-bounce [animation-delay:0.4s]"></div>
                    </div>
                    <span x-show="toolStatusLabel" x-text="toolStatusLabel" class="text-[11px] font-bold text-amber-700"></span>
                </div>
            </div>
            <div id="{{ $anchorId }}-chat-anchor" class="h-px w-full"></div>
        </div>

        <!-- Input Area -->
        <div class="p-4 bg-white border-t border-[#F0E6D2] flex gap-2">
            <input type="text" x-model="message" @keydown.enter="send()"
                   placeholder="Ask a question or request an action..."
                   class="flex-1 bg-[#FAFAFA] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-[#3E2723] transition-all"
                   :disabled="streaming">
            <button @click="send()"
                    class="bg-[#3E2723] text-white p-3 rounded-xl hover:bg-[#271815] transition shadow-lg active:scale-90 disabled:opacity-50"
                    :disabled="streaming || !message.trim()">
                <x-lucide-send class="w-5 h-5" />
            </button>
        </div>
    </div>

    <!-- Toggle Button Wrapper -->
    <div class="flex justify-end w-full">
        <button @click="toggle()"
                @pointerdown="if(!open) startDrag($event)"
                class="w-16 h-16 bg-[#3E2723] hover:bg-[#271815] text-white rounded-full shadow-2xl flex items-center justify-center transition-all hover:scale-110 active:scale-95 group relative"
                :class="!open ? 'cursor-move' : ''">
            {{-- Both icons are absolutely positioned so they occupy the same
                 spot and neither contributes to layout. As in-flow siblings the
                 two transitions overlap — one leaving, one entering — so for
                 ~200ms the button held BOTH icons side by side in its flex row,
                 squeezing them apart and then snapping back once the leaving one
                 was removed. Stacked, the rotate/scale cross-fade reads as one
                 icon turning into the other, which is what it was always meant
                 to look like. --}}
            <div x-show="!open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -rotate-45 scale-75"
                 x-transition:enter-end="opacity-100 rotate-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 rotate-0 scale-100"
                 x-transition:leave-end="opacity-0 rotate-45 scale-75"
                 class="absolute inset-0 flex items-center justify-center">
                <x-lucide-bot class="w-8 h-8 group-hover:rotate-12 transition-transform" />
            </div>
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 rotate-45 scale-75"
                 x-transition:enter-end="opacity-100 rotate-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 rotate-0 scale-100"
                 x-transition:leave-end="opacity-0 -rotate-45 scale-75"
                 class="absolute inset-0 flex items-center justify-center">
                <x-lucide-chevron-down class="w-8 h-8" />
            </div>

            <!-- Notification Dot -->
            <div class="absolute -top-1 -right-1 w-5 h-5 bg-amber-500 border-4 border-[#FDF8F5] rounded-full"></div>
        </button>
    </div>
</div>
@endif

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('agentChat', (config) => ({
        endpoint: config.endpoint,
        csrf: config.csrf,
        csrfToken: config.csrfToken,
        rateLimitMessage: config.rateLimitMessage,
        historyEnabled: config.historyEnabled,

        // --- Learning loop: rating and correcting replies ---
        audience: config.audience,
        feedbackEndpoint: config.feedbackEndpoint,
        // Keyed by message index. Deliberately per-page-load rather than
        // persisted: re-rating the same reply after a refresh is harmless, and
        // storing this would mean another thing to keep in sync with history.
        rated: {},
        corrected: {},
        correcting: null,
        correctionText: '',

        /** The question this reply was answering — walk back to the last user turn. */
        askedBefore(index) {
            for (let i = index - 1; i >= 0; i--) {
                if (this.history[i] && this.history[i].role === 'user') {
                    return this.history[i].content;
                }
            }
            return '';
        },

        async rate(index, sentiment) {
            if (this.rated[index] !== undefined) return;
            // Optimistic: the thumb acknowledges immediately. A rating that
            // fails to reach the server must never interrupt a conversation,
            // which is the whole reason nothing here surfaces an error.
            this.rated[index] = sentiment;

            await this.sendFeedback({
                sentiment,
                user_message: this.askedBefore(index),
                assistant_reply: (this.history[index] && this.history[index].content) || '',
            });
        },

        async submitCorrection(index) {
            const note = this.correctionText.trim();
            if (!note) return;

            this.corrected[index] = true;
            this.correcting = null;
            this.correctionText = '';

            // sentiment 0: a correction is not "bad", it is "here is better".
            await this.sendFeedback({
                sentiment: 0,
                user_message: this.askedBefore(index),
                assistant_reply: (this.history[index] && this.history[index].content) || '',
                note,
            });
        },

        async sendFeedback(payload) {
            try {
                await fetch(this.feedbackEndpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        audience: this.audience,
                        conversation_id: this.conversationId,
                        ...payload,
                    }),
                });
            } catch (e) {
                // Swallowed on purpose — see rate() above.
            }
        },
        context: config.anchorId,
        storageKey: 'agentChatHistory:' + config.anchorId,
        conversationIdStorageKey: 'agentChatConversationId:' + config.anchorId,

        open: false,
        message: '',
        thinking: false,

        // True for the whole request, where `thinking` is only true until the
        // first token lands. Everything that must stay locked until the reply is
        // actually finished reads this one — sending a second message mid-stream
        // used to be possible the moment text started, interleaving two replies
        // into one transcript.
        streaming: false,

        // How long a silent stream is allowed to stay open.
        //
        // The worst legitimate gap is a provider stalling for its full ~18s
        // stream timeout and the next model in the cascade then taking a few
        // seconds to produce its first token — a bit over 20s of genuine
        // silence, during which nothing is wrong. Sized above that with room to
        // spare, while still well under the server's 60s conversation budget so
        // a truly dead socket does not sit there for a minute.
        IDLE_TIMEOUT_MS: 35000,
        toolStatusLabel: null,
        history: [
            { kind: 'text', role: 'assistant', content: config.greeting, isGreeting: true }
        ],
        pendingActions: [],
        resolvingId: null,

        // Server-side conversation history (historyEnabled only).
        conversationId: null,
        showHistory: false,
        loadingHistory: false,
        conversationList: [],

        // Draggable state
        posX: window.innerWidth - 412,
        posY: window.innerHeight - 80,
        isDragging: false,
        dragStartX: 0,
        dragStartY: 0,
        dragMoved: false,
        initialX: 0,
        initialY: 0,

        init() {
            // Restores the conversation across full page navigations (this app has no
            // SPA routing, so every link click destroys and recreates this component).
            // sessionStorage rather than localStorage: these terminals/kiosks are often
            // shared, so history shouldn't outlive the browser tab.
            try {
                const saved = sessionStorage.getItem(this.storageKey);
                if (saved) {
                    const parsed = JSON.parse(saved);
                    // Nothing restored is still being written to. If the tab was
                    // closed mid-stream the flag can be sitting in storage, and
                    // it would come back as a caret blinking forever under a
                    // reply that finished minutes ago.
                    if (Array.isArray(parsed) && parsed.length) {
                        this.history = parsed.map(m => m.streaming ? { ...m, streaming: false } : m);
                    }
                }
                if (this.historyEnabled) {
                    const savedId = sessionStorage.getItem(this.conversationIdStorageKey);
                    if (savedId) this.conversationId = parseInt(savedId, 10) || null;
                }
            } catch (e) { /* corrupt/unavailable storage — keep the default greeting */ }

            // posY is the toggle button's own top, regardless of open state —
            // clampPosition() is the only thing that ever adjusts it now.
            this.clampPosition();

            window.addEventListener('resize', () => this.clampPosition());

            this.$watch('history.length', () => this.scrollToBottom());
            this.$watch('thinking', () => this.scrollToBottom());

            // Pending confirmations rendered here can also be resolved elsewhere
            // (the Agent Activity page) — poll so this bubble doesn't keep saying
            // "needs confirmation" forever after that happens. Guests (csrf: false)
            // never get confirm-tier tools, so skip entirely for the portal widget.
            if (this.csrf) {
                setInterval(() => this.syncPendingActions(), 12000);
            }
        },

        // The window shrinks to fit small viewports instead of staying a fixed
        // 380x550 (see the :style bindings above) — position math below always
        // reads these rather than the raw 380/550 numbers, or the widget would
        // be positioned as if still full-size and spill off-screen on a phone.
        chatWidth() {
            return Math.min(380, window.innerWidth - 32);
        },

        chatHeight() {
            // The round toggle button stays visible in the same fixed column
            // below the window even while open (it becomes the collapse
            // control) — reserve its ~80px (button + margin) here too, or a
            // window sized against the full viewport height would push that
            // button off the bottom of a short screen with nowhere to clamp it.
            return Math.min(550, window.innerHeight - 32 - 80 - this.safeBottom());
        },

        // With viewport-fit=cover the layout viewport now runs underneath the iOS
        // home indicator, so window.innerHeight includes ground the widget must
        // not park on. Read once per call rather than cached: the inset changes
        // on rotation, and this is a cheap style read on an already-styled root.
        safeBottom() {
            const raw = getComputedStyle(document.documentElement)
                .getPropertyValue('--lk-safe-bottom');
            const px = parseFloat(raw);

            return Number.isFinite(px) ? px : 0;
        },

        // Shared bound, used everywhere position is set WITHOUT being a direct
        // drag result (initial load, toggle-open/close, window resize) — a
        // fixed-position widget that only ever clamped its upper bound could
        // end up off-screen with no way back once already there; this clamps
        // both directions so it always self-corrects.
        clampPosition() {
            const maxX = Math.max(16, window.innerWidth - this.chatWidth() - 16);

            // posY is the toggle button's top. The panel now hangs ABOVE it
            // (absolute bottom-full + mb-4), so when open the button needs that
            // much clear space overhead or the panel runs off the top of the
            // screen — hence a lower bound that depends on open state, where the
            // upper bound used to.
            const minY = this.open ? this.chatHeight() + 16 + 16 : 16;
            const maxY = Math.max(minY, window.innerHeight - 80 - this.safeBottom());

            this.posX = Math.min(Math.max(this.posX, 16), maxX);
            this.posY = Math.min(Math.max(this.posY, minY), maxY);
        },

        startDrag(e) {
            this.isDragging = true;
            this.dragMoved = false;
            this.initialX = e.clientX;
            this.initialY = e.clientY;
            this.dragStartX = e.clientX - this.posX;
            this.dragStartY = e.clientY - this.posY;
        },

        onDrag(e) {
            if (!this.isDragging) return;

            const currentX = e.clientX;
            const currentY = e.clientY;

            if (Math.abs(currentX - this.initialX) > 10 || Math.abs(currentY - this.initialY) > 10) {
                this.dragMoved = true;
            }

            this.posX = currentX - this.dragStartX;
            this.posY = currentY - this.dragStartY;
        },

        stopDrag() {
            if (!this.isDragging) return;

            // onDrag deliberately doesn't clamp — clamping mid-drag fights the
            // pointer. Clamping on release instead is what keeps the widget
            // reachable, and it matters more now that the panel hangs above the
            // button: dragging the header toward the top of the screen would
            // otherwise push the panel off it with no way back.
            setTimeout(() => {
                this.isDragging = false;
                this.clampPosition();
            }, 50);
        },

        toggle() {
            if (this.dragMoved) {
                this.dragMoved = false;
                return;
            }

            this.open = !this.open;
            // No posY compensation any more: the panel is out of flow, so the
            // button holds its position and only clamping can move it (and only
            // when a short viewport genuinely has no room for the panel above).
            this.clampPosition();
        },

        handleExternalOpen(prompt) {
            if (!this.open) {
                this.open = true;
                this.clampPosition();
            }
            this.message = prompt;
            this.$nextTick(() => this.send());
        },

        async send() {
            if (!this.message.trim() || this.thinking || this.streaming) return;

            const userMsg = this.message;
            // Captured before pushing the new turn below, so this never has to
            // guess which entries are "real" — no positional slicing needed,
            // which matters once history can also be a server-loaded past
            // conversation with no synthetic greeting bubble at index 0.
            // Excludes falsy content too: a tool-only turn with no reply text
            // can end up stored (or cached in sessionStorage from before that
            // was guarded server-side) with content null/'' — sending that
            // back fails the server's `history.*.content` string validation
            // and 422s every message for the rest of that conversation.
            // Proactively bounded here too (not just server-side via
            // ConversationHistoryService::slidingWindow()) so a long-running
            // conversation doesn't keep growing the request payload forever —
            // this is a size optimization, not the actual safety boundary,
            // since the server never trusts the client to have done it.
            const historyForRequest = this.history
                .filter(m => m.kind === 'text' && !m.isGreeting && m.content)
                .map(m => ({ role: m.role, content: m.content }))
                .slice(-30);

            this.history.push({ kind: 'text', role: 'user', content: userMsg });
            this.message = '';
            this.thinking = true;
            this.streaming = true;
            this.toolStatusLabel = null;
            this.scrollToBottom();
            this.save();

            // An IDLE timeout, not a total one, and that distinction is the whole
            // bug this replaced.
            //
            // This used to be a flat `setTimeout(abort, 20000)` armed once when
            // the request started. The server is allowed far longer than that —
            // ToolCallOrchestrator budgets 60s for a conversation, and each of up
            // to 5 round trips gets its own ~18s provider cascade — so any answer
            // that took more than 20 seconds of wall clock was killed by the
            // browser *while it was still streaming perfectly well*. The bubble
            // stopped mid-sentence, and because the server carried on and
            // persisted the finished reply, refreshing the page showed the rest
            // of it. Exactly the reported symptom.
            //
            // Rearming on every chunk means "no data for a while" ends the
            // request, which is what a stall actually looks like, while a long
            // answer that is still arriving is left alone. The absolute ceiling
            // below is only a backstop against a socket that dribbles forever.
            const controller = new AbortController();
            let idleTimer = null;

            const giveUpAfterSilence = () => {
                clearTimeout(idleTimer);
                idleTimer = setTimeout(() => controller.abort(), this.IDLE_TIMEOUT_MS);
            };

            // Comfortably past the server's own 60s conversation budget, so the
            // client is never the first to give up on a request that is going to
            // finish.
            const hardStop = setTimeout(() => controller.abort(), 75000);

            giveUpAfterSilence();

            // The in-progress assistant bubble streamed text gets appended into.
            // Stays null until the first real content delta arrives — a round
            // that's only resolving tool calls never creates one, so the
            // "Typing..." indicator keeps showing until genuine reply text starts.
            let assistantEntry = null;

            try {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'text/event-stream',
                    // Laravel's expectsJson() only checks Accept for a "json" substring, which
                    // "text/event-stream" doesn't contain — X-Requested-With covers the ajax()
                    // branch of that check instead, so an expired session gets a clean 401
                    // rather than a redirect that stores this streaming URL as the post-login
                    // destination (see: pending-count "intended URL" bug).
                    'X-Requested-With': 'XMLHttpRequest',
                };
                if (this.csrf) headers['X-CSRF-TOKEN'] = this.csrfToken;

                const response = await fetch(this.endpoint, {
                    method: 'POST',
                    headers,
                    signal: controller.signal,
                    body: JSON.stringify({
                        message: userMsg,
                        // Only replay plain conversational turns as history — executed/pending
                        // entries aren't natural-language turns and would confuse the model.
                        history: historyForRequest,
                        conversation_id: this.historyEnabled ? this.conversationId : undefined,
                    })
                });

                if (response.status === 429) {
                    this.history.push({ kind: 'text', role: 'assistant', content: this.rateLimitMessage });
                    return;
                }

                if (response.status === 401) {
                    this.history.push({ kind: 'text', role: 'assistant', content: 'Your session expired due to inactivity — please refresh the page and log in again.' });
                    return;
                }

                if (!response.ok || !response.body) {
                    throw new Error('Bad response');
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    // Data arrived, so the stream is alive — push the deadline
                    // back rather than counting down toward killing a working
                    // response. Done on the raw chunk, not on parsed events: SSE
                    // keep-alives and partial frames are evidence of life too.
                    giveUpAfterSilence();

                    buffer += decoder.decode(value, { stream: true });

                    let boundary;
                    while ((boundary = buffer.indexOf('\n\n')) !== -1) {
                        const rawEvent = buffer.slice(0, boundary);
                        buffer = buffer.slice(boundary + 2);

                        const line = rawEvent.split('\n').find(l => l.startsWith('data:'));
                        if (!line) continue;

                        const json = line.slice(5).trim();
                        if (!json) continue;

                        let event;
                        try { event = JSON.parse(json); } catch (e) { continue; }

                        if (event.type === 'tool_start') {
                            // Fires before the tool actually runs — including for one that
                            // ends up needing confirmation — so this is "what's happening
                            // right now", not "this succeeded". The thinking dots stay
                            // visible; this just adds a label next to them.
                            this.toolStatusLabel = this.labelForTool(event.tool);
                        } else if (event.type === 'delta') {
                            if (!assistantEntry) {
                                // `streaming` stays true here where `thinking`
                                // goes false. The dots are replaced by the
                                // caret on this bubble — before, nothing at all
                                // marked the reply as unfinished, so a pause
                                // between tokens or a tool call part-way through
                                // an answer was indistinguishable from a hang.
                                assistantEntry = { kind: 'text', role: 'assistant', content: '', streaming: true };
                                this.history.push(assistantEntry);
                                this.thinking = false;
                            }
                            this.toolStatusLabel = null;
                            assistantEntry.content += event.text;
                            this.scrollToBottom();
                        } else if (event.type === 'meta') {
                            if (this.historyEnabled && event.conversation_id) {
                                this.conversationId = event.conversation_id;
                            }

                            // meta.reply is authoritative: it's the orchestrator's final
                            // answer and exactly what gets persisted to conversation
                            // history. The streamed deltas are NOT guaranteed to equal
                            // it — AIService passes the same onTextDelta into every
                            // model attempt in the gemini->groq->openrouter cascade, so
                            // a provider that emits some text and then fails mid-stream
                            // leaves that partial text in the bubble and the retry's
                            // text lands on top of it. That produced replies that were
                            // visibly truncated or duplicated, and made the bubble
                            // disagree with what a history reload would show.
                            if (event.reply) {
                                if (assistantEntry) {
                                    if (assistantEntry.content !== event.reply) {
                                        assistantEntry.content = event.reply;
                                    }
                                } else {
                                    this.history.push({ kind: 'text', role: 'assistant', content: event.reply });
                                }
                            }

                            (event.executed || []).forEach(e => {
                                this.history.push({ kind: 'executed', tool: e.tool, message: (e.result && e.result.message) || 'Done.' });
                            });

                            (event.pending || []).forEach(p => {
                                const entry = { kind: 'pending', tool: p.tool, arguments: p.arguments, tier: p.tier, audit_id: p.audit_id, resolved: false, resolution: null, resolutionMessage: null };
                                this.history.push(entry);
                                this.pendingActions.push(entry);
                            });
                        }
                    }
                }
            } catch (error) {
                // A stream that produced text and then died is a different event
                // from one that never connected, and saying "having trouble
                // connecting" under half an answer is plainly untrue. The reply
                // is finished and stored server-side either way, so the useful
                // thing to say is where to find the rest of it.
                const cutOffMidReply = assistantEntry && assistantEntry.content;

                const message = cutOffMidReply
                    ? '_(The connection dropped part-way through this reply. It finished on the server — reopen the chat or refresh to see all of it.)_'
                    : (error.name === 'AbortError'
                        ? "That's taking longer than expected — please try again."
                        : "I'm sorry, I'm having trouble connecting right now.");

                this.history.push({ kind: 'text', role: 'assistant', content: message });
            } finally {
                clearTimeout(idleTimer);
                clearTimeout(hardStop);
                // However the stream ended, the bubble is no longer being
                // written to — the caret must not be left blinking on it.
                if (assistantEntry) assistantEntry.streaming = false;
                this.streaming = false;
                this.thinking = false;
                this.toolStatusLabel = null;
                this.scrollToBottom();
                this.save();
            }
        },

        async syncPendingActions() {
            const unresolved = this.history.filter(m => m.kind === 'pending' && !m.resolved);
            if (unresolved.length === 0) return;

            try {
                const ids = unresolved.map(m => m.audit_id).join(',');
                const response = await fetch(`{{ route('admin.ai.actions.statuses') }}?ids=${ids}`, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) return;

                const results = await response.json();
                let changed = false;

                results.forEach(r => {
                    if (r.status === 'proposed') return;
                    const entry = unresolved.find(m => m.audit_id === r.id);
                    if (!entry) return;

                    entry.resolved = true;
                    entry.resolution = r.status === 'executed' ? 'approved' : (r.status === 'rejected' ? 'rejected' : 'failed');
                    entry.resolutionMessage = r.status === 'rejected'
                        ? (r.approved_by ? `Rejected by ${r.approved_by}.` : 'Rejected.')
                        : r.message;
                    changed = true;
                });

                if (changed) this.save();
            } catch (error) {
                // Silent — this is a background sync, not a user-initiated action.
            }
        },

        async confirmAction(entry) {
            if (this.resolvingId) return;
            this.resolvingId = entry.audit_id;
            try {
                const headers = { 'Accept': 'application/json' };
                if (this.csrf) headers['X-CSRF-TOKEN'] = this.csrfToken;
                const response = await fetch(`/admin/ai/actions/${entry.audit_id}/confirm`, { method: 'POST', headers });
                if (response.status === 429) {
                    this.toast('error', this.rateLimitMessage);
                    return;
                }
                const data = await response.json();
                entry.resolved = true;
                entry.resolution = data.success ? 'approved' : 'failed';
                entry.resolutionMessage = data.message;
                if (!data.success) this.toast('error', data.message || 'Could not confirm this action.');
            } catch (error) {
                this.toast('error', 'Could not reach the server to confirm this action.');
            } finally {
                this.resolvingId = null;
                this.scrollToBottom();
                this.save();
            }
        },

        async rejectAction(entry) {
            if (this.resolvingId) return;
            this.resolvingId = entry.audit_id;
            try {
                const headers = { 'Accept': 'application/json' };
                if (this.csrf) headers['X-CSRF-TOKEN'] = this.csrfToken;
                const response = await fetch(`/admin/ai/actions/${entry.audit_id}/reject`, { method: 'POST', headers });
                if (response.status === 429) {
                    this.toast('error', this.rateLimitMessage);
                    return;
                }
                const data = await response.json();
                entry.resolved = true;
                entry.resolution = 'rejected';
                entry.resolutionMessage = data.message;
            } catch (error) {
                this.toast('error', 'Could not reach the server to reject this action.');
            } finally {
                this.resolvingId = null;
                this.scrollToBottom();
                this.save();
            }
        },

        formatArgs(args) {
            if (!args || Object.keys(args).length === 0) return '';
            return Object.entries(args)
                .map(([key, value]) => key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) + ': ' + value)
                .join(', ');
        },

        // Friendly labels for the live "tool_start" status shown next to the
        // thinking dots. Deliberately falls back to a generic label for any
        // tool name not listed here, rather than showing nothing — a new
        // AgentTool class never needs a frontend change just to avoid this
        // looking broken.
        labelForTool(toolName) {
            const labels = {
                checkStockLevels: 'Checking stock levels…',
                getActiveSessions: 'Looking up connected devices…',
                getTrafficStats: 'Checking network traffic…',
                getSalesSummary: 'Pulling up sales figures…',
                getAnomalySignals: 'Scanning for anomalies…',
                listSupplierPoDrafts: 'Checking purchase order drafts…',
                shiftHandoffSummary: 'Summarizing the shift…',
                lookupVoucher: 'Looking up that voucher…',
                checkMySession: 'Checking your session…',
                restockIngredient: 'Updating stock…',
                voidSale: 'Voiding that sale…',
                draftSupplierPo: 'Drafting a purchase order…',
                sendSupplierPo: 'Sending the purchase order…',
                generateVoucherBatch: 'Generating vouchers…',
                blockDevice: 'Blocking that device…',
                unblockDevice: 'Unblocking that device…',
                setSessionBandwidthTier: 'Adjusting bandwidth…',
                suggestCategoryContent: 'Writing a suggestion…',
            };

            return labels[toolName] || 'Using a tool…';
        },

        // Minimal hand-rolled formatting instead of a markdown library —
        // this component is reused by the guest portal chat, the highest
        // prompt-injection-exposed surface in the app, so the raw text is
        // ALWAYS HTML-escaped first and only the escaped string is pattern-
        // matched afterward. That ordering is what keeps this safe to pipe
        // into x-html: no raw model-supplied markup can ever reach the DOM
        // unescaped, no matter what a guest gets the model to echo back.
        formatMarkdown(text) {
            if (!text) return '';

            const escaped = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

            const inline = (s) => s
                // Bold before italic, so the ** in **bold** is never consumed
                // as two single-asterisk italic markers.
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/(^|[^*])\*(?!\s)([^*]+?)\*(?!\*)/g, '$1<em>$2</em>')
                .replace(/`([^`]+?)`/g, '<code class="px-1 py-0.5 rounded bg-black/5 font-mono text-[0.9em]">$1</code>');

            // Collapse runs of blank lines: models frequently emit \n\n between
            // every sentence, which as raw <br><br> left the replies looking
            // sparse and "messy" in a narrow chat bubble.
            const lines = escaped.replace(/\n{3,}/g, '\n\n').split('\n');

            return lines
                .map(line => {
                    // Headings would otherwise render as literal "### Text".
                    const heading = line.match(/^\s*#{1,6}\s+(.*)$/);
                    if (heading) {
                        return '<strong class="block mt-2 first:mt-0">' + inline(heading[1]) + '</strong>';
                    }

                    const bullet = line.match(/^\s*[-*]\s+(.*)$/);
                    if (bullet) {
                        return '<span class="block pl-3 -indent-3">• ' + inline(bullet[1]) + '</span>';
                    }

                    // Numbered lists: keep the model's own numbering, just
                    // align the wrap the same way bullets are aligned.
                    const numbered = line.match(/^\s*(\d+)[.)]\s+(.*)$/);
                    if (numbered) {
                        return '<span class="block pl-4 -indent-4">' + numbered[1] + '. ' + inline(numbered[2]) + '</span>';
                    }

                    return inline(line);
                })
                // Block-level lines bring their own layout; only genuine
                // inline runs still need an explicit line break between them.
                .reduce((html, line, i, all) => {
                    if (i === 0) return line;
                    const prevIsBlock = /^<(strong class|span class)/.test(all[i - 1]);
                    const thisIsBlock = /^<(strong class|span class)/.test(line);
                    return html + (prevIsBlock || thisIsBlock ? '' : '<br>') + line;
                }, '');
        },

        toast(icon, title) {
            if (typeof Swal === 'undefined') return;
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon,
                title,
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
            });
        },

        scrollToBottom() {
            this.$nextTick(() => {
                setTimeout(() => {
                    const anchor = document.getElementById('{{ $anchorId }}-chat-anchor');
                    if (anchor) {
                        anchor.scrollIntoView({ behavior: 'smooth', block: 'end' });
                    }
                }, 50);
            });
        },

        save() {
            try {
                sessionStorage.setItem(this.storageKey, JSON.stringify(this.history));
                if (this.historyEnabled) {
                    if (this.conversationId) {
                        sessionStorage.setItem(this.conversationIdStorageKey, String(this.conversationId));
                    } else {
                        sessionStorage.removeItem(this.conversationIdStorageKey);
                    }
                }
            } catch (e) { /* storage full/unavailable — history just won't survive navigation */ }
        },

        toggleHistory() {
            this.showHistory = !this.showHistory;
            if (this.showHistory) this.loadHistoryList();
        },

        async loadHistoryList() {
            this.loadingHistory = true;
            try {
                const response = await fetch(`{{ route('ai.conversations.index') }}?context=${this.context}`, {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) return;
                this.conversationList = await response.json();
            } catch (error) {
                // Silent — the panel just shows its empty state.
            } finally {
                this.loadingHistory = false;
            }
        },

        async openConversation(id) {
            try {
                const response = await fetch(`/ai/conversations/${id}`, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) return;
                const data = await response.json();
                if (Array.isArray(data.messages) && data.messages.length) {
                    this.history = data.messages;
                    this.conversationId = id;
                    this.showHistory = false;
                    this.save();
                    this.scrollToBottom();
                }
            } catch (error) {
                this.toast('error', 'Could not load that conversation.');
            }
        },

        newConversation() {
            this.history = [{ kind: 'text', role: 'assistant', content: {{ Illuminate\Support\Js::from($greeting) }}, isGreeting: true }];
            this.conversationId = null;
            this.showHistory = false;
            this.save();
        },

        async deleteConversation(id) {
            try {
                const headers = { 'Accept': 'application/json' };
                if (this.csrf) headers['X-CSRF-TOKEN'] = this.csrfToken;
                await fetch(`/ai/conversations/${id}`, { method: 'DELETE', headers });
                this.conversationList = this.conversationList.filter(c => c.id !== id);
                if (this.conversationId === id) this.newConversation();
            } catch (error) {
                this.toast('error', 'Could not delete that conversation.');
            }
        },

        formatRelativeTime(iso) {
            if (!iso) return '';
            const diffMs = Date.now() - new Date(iso).getTime();
            const minutes = Math.round(diffMs / 60000);
            if (minutes < 1) return 'Just now';
            if (minutes < 60) return `${minutes}m ago`;
            const hours = Math.round(minutes / 60);
            if (hours < 24) return `${hours}h ago`;
            const days = Math.round(hours / 24);
            if (days < 7) return `${days}d ago`;
            return new Date(iso).toLocaleDateString();
        }
    }));
});
</script>
