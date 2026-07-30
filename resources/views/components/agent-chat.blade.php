@props([
    'endpoint',
    'title' => 'Barista AI',
    'subtitle' => '',
    'greeting' => "Hello! I'm Barista AI. How can I help?",
    'anchorId' => 'agent',
    'mode' => 'floating',
    'csrf' => true,
    'rateLimitMessage' => "I'm a bit busy right now — please try again in a moment.",
])

@if($mode === 'embedded')
    {{-- Embedded mode: no header/toggle/drag chrome — fills its parent's existing container. --}}
    <div x-data="agentChat({
            endpoint: @js($endpoint),
            greeting: @js($greeting),
            csrf: @js($csrf),
            csrfToken: @js(csrf_token()),
            rateLimitMessage: @js($rateLimitMessage),
            anchorId: @js($anchorId),
        })"
        class="flex-1 min-h-0 flex flex-col"
        @open-agent-chat.window="handleExternalOpen($event.detail.prompt)"
        @portal-tab-changed.window="scrollToBottom()">

        <div class="overflow-y-auto space-y-3 pr-1 w-full flex flex-col justify-start z-10 max-h-full no-scrollbar flex-1 min-h-0" id="{{ $anchorId }}-chat-history">
            <template x-for="(msg, index) in history" :key="index">
                <div class="anim-pop-in">
                    <template x-if="msg.kind === 'text'">
                        <div class="p-3 rounded-2xl shadow-sm text-xs font-medium relative w-fit max-w-[85%] break-words whitespace-normal mx-1"
                             :class="msg.role === 'user' ? 'bg-[#3E2723] text-white self-end rounded-br-sm' : 'bg-white text-[#4A3B32] border border-[#F0E6D2] self-start rounded-bl-sm'">
                            <span x-text="msg.content" class="leading-relaxed"></span>
                        </div>
                    </template>

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
            <div x-show="thinking" class="anim-pop-in bg-white p-3 rounded-2xl rounded-bl-sm shadow-sm border border-[#F0E6D2] self-start w-fit mx-1">
                <span class="text-[9px] text-[#8D6E63] font-black tracking-widest uppercase animate-pulse">Typing...</span>
            </div>
            <div id="{{ $anchorId }}-chat-anchor" class="h-1 w-full"></div>
        </div>

        <div class="flex gap-2 shrink-0 pt-3">
            <input type="text" x-model="message" @keydown.enter="send()" placeholder="Ask something..."
                   class="flex-1 bg-white border-2 border-[#F0E6D2] rounded-2xl px-4 py-3 text-xs font-bold focus:outline-none focus:border-[#3E2723] transition-all shadow-sm text-[#3E2723] placeholder:font-medium"
                   :disabled="thinking">
            <button @click="send()" class="bg-[#3E2723] text-white px-4 py-3 rounded-2xl hover:bg-[#271815] transition shadow-lg active:scale-95 disabled:opacity-50 flex items-center justify-center shrink-0" :disabled="thinking || !message.trim()">
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
    })"
     class="fixed flex flex-col"
     :style="`left: ${posX}px; top: ${posY}px; width: 380px; position: fixed !important; z-index: 9999 !important; bottom: auto !important; right: auto !important; transition: ${isDragging ? 'none' : 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)'}; touch-action: none;`"
     @pointermove.window="onDrag($event)"
     @pointerup.window="stopDrag()"
     @pointercancel.window="stopDrag()"
     @open-agent-chat.window="handleExternalOpen($event.detail.prompt)">

    <!-- Chat Window -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-90 translate-y-10"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-[400ms]"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-90 translate-y-10"
         class="mb-4 w-full h-[550px] bg-white rounded-[2rem] shadow-2xl border border-[#F0E6D2] overflow-hidden flex flex-col shadow-amber-900/10"
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
            <button @click="open = false; posY += 566" class="text-amber-200 hover:text-white transition">
                <x-lucide-x class="w-6 h-6" />
            </button>
        </div>

        <!-- Messages Area -->
        <div class="flex-1 overflow-y-auto p-6 space-y-4 bg-[#FDF8F5]" id="{{ $anchorId }}-chat-history">
            <template x-for="(msg, index) in history" :key="index">
                <div class="anim-pop-in">
                    <!-- Plain text turn -->
                    <template x-if="msg.kind === 'text'">
                        <div class="flex flex-col" :class="msg.role === 'user' ? 'items-end' : 'items-start'">
                            <div class="max-w-[85%] p-4 rounded-2xl text-xs font-medium leading-relaxed shadow-sm"
                                 :class="msg.role === 'user' ? 'bg-[#3E2723] text-white rounded-tr-none' : 'bg-white text-[#4A3B32] border border-[#F0E6D2] rounded-tl-none'">
                                <span x-html="msg.content.replace(/\n/g, '<br>')"></span>
                            </div>
                            <span class="text-[8px] font-black uppercase tracking-widest text-[#A1887F] mt-1.5 mx-1" x-text="msg.role === 'user' ? 'You' : @js($title)"></span>
                        </div>
                    </template>

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
                <div class="bg-white border border-[#F0E6D2] p-4 rounded-2xl rounded-tl-none shadow-sm">
                    <div class="flex gap-1">
                        <div class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-bounce"></div>
                        <div class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-bounce [animation-delay:0.2s]"></div>
                        <div class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-bounce [animation-delay:0.4s]"></div>
                    </div>
                </div>
            </div>
            <div id="{{ $anchorId }}-chat-anchor" class="h-px w-full"></div>
        </div>

        <!-- Input Area -->
        <div class="p-4 bg-white border-t border-[#F0E6D2] flex gap-2">
            <input type="text" x-model="message" @keydown.enter="send()"
                   placeholder="Ask a question or request an action..."
                   class="flex-1 bg-[#FAFAFA] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-[#3E2723] transition-all"
                   :disabled="thinking">
            <button @click="send()"
                    class="bg-[#3E2723] text-white p-3 rounded-xl hover:bg-[#271815] transition shadow-lg active:scale-90 disabled:opacity-50"
                    :disabled="thinking || !message.trim()">
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
            <div x-show="!open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 -rotate-45 scale-75"
                 x-transition:enter-end="opacity-100 rotate-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 rotate-0 scale-100"
                 x-transition:leave-end="opacity-0 rotate-45 scale-75"
                 class="flex items-center justify-center">
                <x-lucide-bot class="w-8 h-8 group-hover:rotate-12 transition-transform" />
            </div>
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 rotate-45 scale-75"
                 x-transition:enter-end="opacity-100 rotate-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 rotate-0 scale-100"
                 x-transition:leave-end="opacity-0 -rotate-45 scale-75"
                 class="flex items-center justify-center">
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
        storageKey: 'agentChatHistory:' + config.anchorId,

        open: false,
        message: '',
        thinking: false,
        history: [
            { kind: 'text', role: 'assistant', content: config.greeting }
        ],
        pendingActions: [],
        resolvingId: null,

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
                    if (Array.isArray(parsed) && parsed.length) this.history = parsed;
                }
            } catch (e) { /* corrupt/unavailable storage — keep the default greeting */ }

            if (this.open) {
                this.posY = window.innerHeight - 646;
            }

            window.addEventListener('resize', () => {
                if (this.posX > window.innerWidth - 412) this.posX = window.innerWidth - 412;
                if (this.posY > window.innerHeight - 80) this.posY = window.innerHeight - 80;
            });

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
            setTimeout(() => { this.isDragging = false; }, 50);
        },

        toggle() {
            if (this.dragMoved) {
                this.dragMoved = false;
                return;
            }

            this.open = !this.open;
            if (this.open) {
                this.posY -= 566;
            } else {
                this.posY += 566;
            }
        },

        handleExternalOpen(prompt) {
            if (!this.open) {
                this.open = true;
                this.posY -= 566;
            }
            this.message = prompt;
            this.$nextTick(() => this.send());
        },

        async send() {
            if (!this.message.trim() || this.thinking) return;

            const userMsg = this.message;
            this.history.push({ kind: 'text', role: 'user', content: userMsg });
            this.message = '';
            this.thinking = true;
            this.scrollToBottom();
            this.save();

            // Client-side ceiling matching the worst-case server-side provider
            // cascade, so a dead request reads as "slow, try again" rather than
            // hanging indefinitely with no feedback.
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 20000);

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
                        history: this.history
                            .filter(m => m.kind === 'text')
                            .slice(1, -1)
                            .map(m => ({ role: m.role, content: m.content })),
                    })
                });

                if (response.status === 429) {
                    this.history.push({ kind: 'text', role: 'assistant', content: this.rateLimitMessage });
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

                        if (event.type === 'delta') {
                            if (!assistantEntry) {
                                assistantEntry = { kind: 'text', role: 'assistant', content: '' };
                                this.history.push(assistantEntry);
                                this.thinking = false;
                            }
                            assistantEntry.content += event.text;
                            this.scrollToBottom();
                        } else if (event.type === 'meta') {
                            // Only push meta.reply as a fresh bubble if nothing streamed —
                            // when it did, meta.reply is the same text already rendered live.
                            if (!assistantEntry && event.reply) {
                                this.history.push({ kind: 'text', role: 'assistant', content: event.reply });
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
                const message = error.name === 'AbortError'
                    ? "That's taking longer than expected — please try again."
                    : "I'm sorry, I'm having trouble connecting right now.";
                this.history.push({ kind: 'text', role: 'assistant', content: message });
            } finally {
                clearTimeout(timeoutId);
                this.thinking = false;
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
            } catch (e) { /* storage full/unavailable — history just won't survive navigation */ }
        }
    }));
});
</script>
