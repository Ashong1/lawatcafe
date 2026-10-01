// The Barista AI chat (x-data="agentChat(...)"), shared by the admin, staff
// and guest portal pages. Lives in the bundle rather than inline in the Blade
// component so phones cache it once instead of re-downloading ~44 KB on every
// portal page. Server values arrive through the component's config object.
export default function registerAgentChat(Alpine) {
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
        // Attached photo: the full downscaled data URL (sent once, never
        // saved) and a small thumbnail (shown in the bubble, saved in history).
        image: null,
        imageThumb: null,
        imageError: null,
        thinking: false,

        // True for the whole request, where `thinking` is only true until the
        // first token lands. Everything that must stay locked until the reply is
        // finished reads this one — otherwise a second message sent mid-stream
        // interleaves two replies into one transcript.
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
        // Set once the user drags the button: from then on it stays where they
        // put it and never dodges the page's avoid zones.
        userPlaced: false,
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
            this.dodgeAvoidZones();
            this.clampPosition();

            window.addEventListener('resize', () => { this.dodgeAvoidZones(); this.clampPosition(); });
            // A page whose avoid zone appears later (the register's View Cart
            // bar shows up with the first item) fires this after the change.
            window.addEventListener('chat-avoid-changed', () => { this.dodgeAvoidZones(); this.clampPosition(); });

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

        // Keeps the toggle button off controls a page marks with
        // data-chat-avoid (the register's cart and Place Order button). Starts
        // from the default bottom-right spot each time so the button returns
        // there once the zone is gone. Beside a tall zone (a sidebar) it moves
        // left of it; otherwise (a bottom bar) it moves above it.
        dodgeAvoidZones() {
            if (this.userPlaced || this.open) return;

            const size = 64;
            const gap = 16;
            const width = this.chatWidth();
            this.posX = window.innerWidth - width - 32;
            this.posY = window.innerHeight - 80 - this.safeBottom();

            for (const el of document.querySelectorAll('[data-chat-avoid]')) {
                if (!el.getClientRects().length) continue;
                const r = el.getBoundingClientRect();
                const left = this.posX + width - size;
                const overlaps = left < r.right && left + size > r.left && this.posY < r.bottom && this.posY + size > r.top;
                if (!overlaps) continue;

                if (r.height > window.innerHeight / 2 && r.left - gap - size >= gap) {
                    this.posX = r.left - gap - width;
                } else {
                    this.posY = r.top - gap - size;
                }
            }
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
            if (this.dragMoved) this.userPlaced = true;
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

        /**
         * Downscale a picked photo in the browser before it is ever sent. A
         * phone photo is 3-8MB; nginx refuses bodies over 1MB, and the model
         * reads a 1280px image just as well. Also makes the bubble thumbnail.
         */
        async attachImage(event) {
            const file = event.target.files && event.target.files[0];
            event.target.value = '';
            await this.attachFile(file);
        },

        // Ctrl+V / Cmd+V of a screenshot or copied image. Text pastes are left
        // alone: only an image item in the clipboard is intercepted. Without
        // this the only way in was the camera button's file picker.
        async pasteImage(event) {
            const items = (event.clipboardData && event.clipboardData.items) || [];
            const item = Array.from(items).find(i => i.kind === 'file' && i.type.startsWith('image/'));
            if (!item) return;
            event.preventDefault();
            await this.attachFile(item.getAsFile());
        },

        // An image file dragged onto the chat window.
        async dropImage(event) {
            const file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
            await this.attachFile(file);
        },

        async attachFile(file) {
            this.imageError = null;
            if (!file) return;
            if (!file.type.startsWith('image/')) {
                this.imageError = 'That file is not a photo.';
                return;
            }
            try {
                const bitmap = await createImageBitmap(file);
                const render = (maxSide, quality) => {
                    const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.round(bitmap.width * scale);
                    canvas.height = Math.round(bitmap.height * scale);
                    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                    return canvas.toDataURL('image/jpeg', quality);
                };
                let full = render(1280, 0.82);
                if (full.length > 950000) full = render(1024, 0.7);
                this.image = full;
                this.imageThumb = render(240, 0.7);
            } catch (e) {
                this.clearImage();
                this.imageError = 'Could not read that photo — try another one.';
            }
        },

        clearImage() {
            this.image = null;
            this.imageThumb = null;
            this.imageError = null;
        },

        async send() {
            if ((!this.message.trim() && !this.image) || this.thinking || this.streaming) return;

            const userMsg = this.message;
            const image = this.image;
            const imageThumb = this.imageThumb;
            // Captured before pushing the new turn, so no positional slicing
            // (a server-loaded conversation has no greeting at index 0). Skips
            // empty content — null/'' fails the server's history.*.content
            // validation and 422s every later message. Bounded here only to keep
            // the payload small; the server applies its own sliding window.
            const historyForRequest = this.history
                .filter(m => m.kind === 'text' && !m.isGreeting && (m.content || m.imageThumb))
                // Earlier photos aren't re-sent; the marker tells the model one existed.
                .map(m => ({ role: m.role, content: m.imageThumb ? ('📷 [photo attached] ' + (m.content || '')).trim() : m.content }))
                .slice(-30);

            this.history.push({ kind: 'text', role: 'user', content: userMsg, imageThumb });
            this.message = '';
            this.clearImage();
            this.thinking = true;
            this.streaming = true;
            this.toolStatusLabel = null;
            this.scrollToBottom();
            this.save();

            // An IDLE timeout, not a total one. The server may legitimately take
            // far longer than any fixed limit — ToolCallOrchestrator budgets 60s
            // per conversation, each of up to 5 round trips with its own ~18s
            // cascade — and a total timeout kills answers that are still
            // streaming fine (the bubble stops mid-sentence while the server
            // saves the full reply). Rearming on every chunk ends only a real
            // stall; the absolute ceiling below is a backstop against a socket
            // that dribbles forever.
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
                        image: image || undefined,
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
                                this.history.push({ kind: 'text', role: 'assistant', content: '', streaming: true });

                                // Read the entry back OUT of the array; don't
                                // mutate the literal we pushed. Alpine stores the
                                // raw object and only hands out a reactive Proxy
                                // when an element is read back through the array,
                                // so mutating the literal updates data but
                                // notifies nothing — the bubble freezes
                                // mid-sentence and even the final meta.reply
                                // repaints nothing (while save() stores the full
                                // text).
                                assistantEntry = this.history[this.history.length - 1];

                                this.thinking = false;
                            }
                            this.toolStatusLabel = null;
                            assistantEntry.content += event.text;
                            this.scrollToBottom(true);
                        } else if (event.type === 'meta') {
                            if (this.historyEnabled && event.conversation_id) {
                                this.conversationId = event.conversation_id;
                            }

                            // meta.reply is authoritative: it's the orchestrator's final
                            // answer and exactly what gets persisted to conversation
                            // history. The streamed deltas are NOT guaranteed to equal
                            // it — AIService passes the same onTextDelta into every
                            // model attempt in the OpenRouter cascade, so
                            // a model that emits some text and then fails mid-stream
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
                                this.history.push({ kind: 'executed', tool: e.tool, label: e.label, message: (e.result && e.result.message) || 'Done.' });
                            });

                            (event.pending || []).forEach(p => {
                                const entry = { kind: 'pending', tool: p.tool, label: p.label, arguments: p.arguments, tier: p.tier, audit_id: p.audit_id, resolved: false, resolution: null, resolutionMessage: null };
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
                const response = await fetch(`${config.statusesUrl}?ids=${ids}`, { headers: { 'Accept': 'application/json' } });
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
                // Arrays read as "a, b, c" — String([...]) gave "a,b,c" with no spaces.
                .map(([key, value]) => key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) + ': ' + (Array.isArray(value) ? value.join(', ') : value))
                .join(', ');
        },

        // A reply of only whitespace (a model that answers with just a tool
        // call sometimes streams a newline) drew an empty bubble with rating
        // thumbs under it.
        hasText(msg) {
            return typeof msg.content === 'string' && msg.content.trim() !== '';
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

        // Scrolls only the chat's own box: scrollIntoView would also move the
        // page, and on the portal that re-arms the phone's pull-to-refresh
        // layer. With onlyIfNearBottom (streamed replies), a guest who has
        // scrolled up to re-read something is left where they are.
        // A quick-question button: the answer is already on the page, so it
        // is shown straight away and never reaches the AI.
        askQuick(i) {
            const reply = (config.quickReplies || [])[i];
            if (!reply || this.streaming) return;
            this.history.push({ kind: 'text', role: 'user', content: reply.label });
            this.history.push({ kind: 'text', role: 'assistant', content: reply.answer, isQuickReply: true });
            this.save();
            this.scrollToBottom();
        },

        scrollToBottom(onlyIfNearBottom = false) {
            this.$nextTick(() => {
                setTimeout(() => {
                    const anchor = document.getElementById(config.anchorId + '-chat-anchor');
                    let box = anchor?.parentElement;
                    while (box && !(/(auto|scroll)/.test(getComputedStyle(box).overflowY) && box.scrollHeight > box.clientHeight)) {
                        box = box.parentElement;
                    }
                    if (!box || box === document.body || box === document.documentElement) return;
                    if (onlyIfNearBottom && box.scrollHeight - box.scrollTop - box.clientHeight > 150) return;
                    box.scrollTo({ top: box.scrollHeight, behavior: 'smooth' });
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
                const response = await fetch(`${config.conversationsUrl}?context=${this.context}`, {
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
            this.history = [{ kind: 'text', role: 'assistant', content: config.greeting, isGreeting: true }];
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
}
