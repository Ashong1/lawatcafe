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
    // Guest portal only: [['label' => ..., 'answer' => ...]] buttons answered
    // on the spot with no AI call (see App\Services\PortalQuickReplies).
    'quickReplies' => [],
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
        statusesUrl: @js(route('admin.ai.actions.statuses')),
        conversationsUrl: @js(route('ai.conversations.index')),
        quickReplies: @js($quickReplies),
        })"
        class="flex-1 min-h-0 flex flex-col"
        @open-agent-chat.window="handleExternalOpen($event.detail.prompt)"
        @portal-tab-changed.window="scrollToBottom()">

        {{-- flex-1 min-h-0 is what gives this box a height smaller than its
             content; max-h-full was removed because a percentage max-height
             against a flex-sized parent is unreliable and it added nothing.

             The scrollbar is deliberately visible (no `no-scrollbar` here):
             hidden, a long conversation gives no cue there is anything above
             and reads as "not scrollable". overscroll-contain stops a flick at
             the top of the history from dragging the whole portal panel. --}}
        <div class="overflow-y-auto overscroll-contain space-y-3 pr-1 w-full flex flex-col justify-start z-10 flex-1 min-h-0" id="{{ $anchorId }}-chat-history">
            <template x-for="(msg, index) in history" :key="index">
                {{-- The bubble's self-end/self-start only works when its parent
                     is a flex container; this wrapper was a plain block, so every
                     message — the guest's included — sat on the left. --}}
                <div class="anim-pop-in flex flex-col" :class="msg.role === 'user' ? 'items-end' : 'items-start'">
                    <template x-if="msg.kind === 'text' && (hasText(msg) || msg.imageThumb)">
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
                        <div class="flex items-start gap-2 max-w-[90%] p-3 rounded-xl text-xs font-bold bg-emerald-50 border border-emerald-200 text-emerald-800 mx-1">
                            <x-lucide-check-circle-2 class="w-4 h-4 shrink-0 mt-0.5" />
                            <span><span class="font-bold" x-text="msg.label || msg.tool"></span>: <span x-text="msg.message"></span></span>
                        </div>
                    </template>

                    <template x-if="msg.kind === 'pending'">
                        <div class="max-w-[90%] p-4 rounded-xl bg-amber-50 border border-amber-300 space-y-2 mx-1">
                            <div class="flex items-center gap-2 text-xs font-bold text-amber-800 uppercase tracking-tighter">
                                <x-lucide-clock class="w-4 h-4 shrink-0" />
                                <span x-text="msg.label || msg.tool"></span>
                            </div>
                            {{-- break-words: a long domain list was one unbreakable line that pushed the card off the panel. --}}
                            <p class="text-xs text-amber-700 font-medium break-words [overflow-wrap:anywhere]" x-text="formatArgs(msg.arguments)"></p>
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
                <span x-show="toolStatusLabel" x-text="toolStatusLabel" class="text-xs font-bold text-amber-700"></span>
            </div>
            <div id="{{ $anchorId }}-chat-anchor" class="h-1 w-full"></div>
        </div>

        @if(!empty($quickReplies))
            <div class="grid grid-cols-2 gap-1.5 shrink-0 pt-3" role="group" aria-label="{{ __('Common questions') }}">
                @foreach($quickReplies as $i => $reply)
                    <button type="button" x-on:click="askQuick({{ $i }})" :disabled="streaming"
                            class="min-h-[40px] px-2 py-1 rounded-2xl border-2 border-[#E6D5C3] bg-white text-sm font-semibold leading-tight text-[#3E2723] active:scale-95 disabled:opacity-50">{{ $reply['label'] }}</button>
                @endforeach
            </div>
        @endif

        <div class="flex gap-2 shrink-0 pt-3">
            {{-- min-w-0: a text input has an intrinsic width and, as a flex item,
                 will not shrink below it. The portal forces inputs to 16px (iOS
                 zoom guard), which widened it past a phone-width card and pushed
                 the send button off the edge. --}}
            <input type="text" x-model="message" @keydown.enter="send()" placeholder="{{ __('Type your question…') }}" aria-label="{{ __('Type your question…') }}"
                   class="flex-1 min-w-0 bg-white border-2 border-[#F0E6D2] rounded-2xl px-4 py-3 text-sm font-semibold focus:outline-none focus:border-[#3E2723] transition-all shadow-sm text-[#3E2723] placeholder:font-medium"
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
        statusesUrl: @js(route('admin.ai.actions.statuses')),
        conversationsUrl: @js(route('ai.conversations.index')),
        quickReplies: @js($quickReplies),
    })"
     class="fixed flex flex-col"
     :style="`left: ${posX}px; top: ${posY}px; width: ${chatWidth()}px; position: fixed !important; z-index: 9999 !important; bottom: auto !important; right: auto !important; transition: ${isDragging ? 'none' : 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)'}; touch-action: none;`"
     @pointermove.window="onDrag($event)"
     @pointerup.window="stopDrag()"
     @pointercancel.window="stopDrag()"
     @open-agent-chat.window="handleExternalOpen($event.detail.prompt)">

    {{-- Chat Window: absolutely positioned above the toggle button, not in flow.
         In flow, the button's position depended on the panel being open and had
         to be compensated in posY — which animates (transition: all 0.3s) while
         display:none lands instantly, so the button swooped on close. Out of
         flow, the button never moves. --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-90 translate-y-10"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-90 translate-y-10"
         class="absolute bottom-full left-0 mb-4 w-full origin-bottom bg-white rounded-[2rem] shadow-2xl border border-[#F0E6D2] overflow-hidden flex flex-col shadow-amber-900/10"
         :style="`height: ${chatHeight()}px`"
         @dragover.prevent @drop.prevent="dropImage($event)"
         style="display: none;">

        <!-- Header (Draggable Handle) -->
        <div class="bg-[#3E2723] p-6 text-white flex items-center justify-between cursor-move select-none"
             @pointerdown="startDrag($event)">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-amber-500 rounded-2xl flex items-center justify-center shadow-lg">
                    <x-lucide-bot class="w-6 h-6 text-[#3E2723]" />
                </div>
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wide">{{ $title }}</h3>
                    @if($subtitle)
                        <p class="text-xs font-bold text-amber-200 uppercase tracking-tighter">{{ $subtitle }}</p>
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
            <div x-show="!loadingHistory && conversationList.length === 0" class="p-4 text-center text-xs font-bold uppercase tracking-wide text-[#6D4C41]">No past conversations yet.</div>
            <template x-for="conv in loadingHistory ? [] : conversationList" :key="conv.id">
                <div @click="openConversation(conv.id)"
                     tabindex="0" role="button" @keydown.enter="openConversation(conv.id)" @keydown.space.prevent="openConversation(conv.id)"
                     class="flex items-center justify-between gap-2 px-4 py-3 border-b border-[#F0E6D2] last:border-0 cursor-pointer hover:bg-[#FDF8F5] transition group"
                     :class="conv.id === conversationId ? 'bg-amber-50' : ''">
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-[#3E2723] truncate" x-text="conv.title || 'Conversation'"></p>
                        <p class="text-xs font-bold uppercase tracking-wide text-[#6D4C41] mt-0.5" x-text="formatRelativeTime(conv.last_message_at)"></p>
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
                    <template x-if="msg.kind === 'text' && (hasText(msg) || msg.imageThumb)">
                        <div class="flex flex-col" :class="msg.role === 'user' ? 'items-end' : 'items-start'">
                            <div class="max-w-[85%] p-4 rounded-2xl text-xs font-medium leading-relaxed shadow-sm"
                                 :class="msg.role === 'user' ? 'bg-[#3E2723] text-white rounded-tr-none' : 'bg-white text-[#4A3B32] border border-[#F0E6D2] rounded-tl-none'">
                                {{-- A small thumbnail only: the full photo is sent once
                                     and never kept, so sessionStorage stays tiny. --}}
                                <template x-if="msg.imageThumb">
                                    <img :src="msg.imageThumb" alt="Attached photo" class="rounded-xl max-h-40 w-auto" :class="msg.content ? 'mb-2' : ''">
                                </template>
                                <span x-html="formatMarkdown(msg.content)"></span>
                                {{-- Still-writing caret — see the note on the
                                     same element in the floating variant. --}}
                                <span x-show="msg.streaming" aria-hidden="true"
                                      class="inline-block w-1.5 h-3 ml-0.5 -mb-0.5 bg-amber-500 rounded-sm animate-pulse"></span>
                            </div>
                            <span class="text-xs font-bold uppercase tracking-wide text-[#6D4C41] mt-1.5 mx-1" x-text="msg.role === 'user' ? 'You' : @js($title)"></span>
                        </div>
                    </template>

                    @include('components.partials.agent-chat-rating')

                    <!-- Executed tool action -->
                    <template x-if="msg.kind === 'executed'">
                        <div class="flex items-start gap-2 max-w-[90%] p-3 rounded-xl text-xs font-bold bg-emerald-50 border border-emerald-200 text-emerald-800">
                            <x-lucide-check-circle-2 class="w-4 h-4 shrink-0 mt-0.5" />
                            <span><span class="font-bold" x-text="msg.label || msg.tool"></span>: <span x-text="msg.message"></span></span>
                        </div>
                    </template>

                    <!-- Pending confirmation -->
                    <template x-if="msg.kind === 'pending'">
                        <div class="max-w-[90%] p-4 rounded-xl bg-amber-50 border border-amber-300 space-y-2">
                            <div class="flex items-center gap-2 text-xs font-bold text-amber-800 uppercase tracking-tighter">
                                <x-lucide-clock class="w-4 h-4 shrink-0" />
                                <span x-text="msg.label || msg.tool"></span>
                                <span class="text-xs font-bold text-amber-600">needs your OK</span>
                            </div>
                            {{-- break-words: a long domain list was one unbreakable line that pushed the card off the panel. --}}
                            <p class="text-xs text-amber-700 font-medium break-words [overflow-wrap:anywhere]" x-text="formatArgs(msg.arguments)"></p>

                            <template x-if="!msg.resolved">
                                <div class="flex gap-2 pt-1">
                                    <button @click="confirmAction(msg)" :disabled="resolvingId === msg.audit_id"
                                            class="flex-1 flex items-center justify-center gap-1 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold uppercase tracking-wide px-3 py-2 rounded-lg transition disabled:opacity-50">
                                        <template x-if="resolvingId === msg.audit_id"><x-lucide-loader-2 class="w-3 h-3 animate-spin" /></template>
                                        <span>Approve</span>
                                    </button>
                                    <button @click="rejectAction(msg)" :disabled="resolvingId === msg.audit_id"
                                            class="flex-1 bg-white hover:bg-red-50 border border-amber-300 text-amber-800 hover:text-red-700 text-xs font-bold uppercase tracking-wide px-3 py-2 rounded-lg transition disabled:opacity-50">
                                        Reject
                                    </button>
                                </div>
                            </template>
                            <template x-if="msg.resolved">
                                <p class="text-xs font-bold uppercase tracking-wide"
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
                    <span x-show="toolStatusLabel" x-text="toolStatusLabel" class="text-xs font-bold text-amber-700"></span>
                </div>
            </div>
            <div id="{{ $anchorId }}-chat-anchor" class="h-px w-full"></div>
        </div>

        <!-- Input Area -->
        {{-- Photo attach is staff/admin/super_admin only: this floating variant is
             never used on the guest portal, and the guest endpoint ignores the
             field anyway. See App\Services\Agent\ChatImage. --}}
        <div class="p-4 bg-white border-t border-[#F0E6D2] space-y-2">
            <div x-show="imageThumb || imageError" style="display: none;" class="flex items-center gap-2">
                <template x-if="imageThumb">
                    <div class="relative">
                        <img :src="imageThumb" alt="Photo to send" class="h-14 w-14 object-cover rounded-lg border border-[#F0E6D2]">
                        <button type="button" @click="clearImage()" aria-label="Remove photo"
                                class="absolute -top-1.5 -right-1.5 bg-[#3E2723] text-white rounded-full p-0.5 shadow">
                            <x-lucide-x class="w-3 h-3" />
                        </button>
                    </div>
                </template>
                <span x-show="imageError" x-text="imageError" class="text-xs font-bold text-red-600"></span>
            </div>
            <div class="flex gap-2">
                <label class="shrink-0 bg-[#FAFAFA] border-2 border-[#F0E6D2] text-[#6D4C41] p-3 rounded-xl hover:border-[#3E2723] transition cursor-pointer flex items-center"
                       :class="streaming ? 'opacity-50 pointer-events-none' : ''" title="Attach a photo — or paste one (Ctrl+V) or drag it here">
                    <x-lucide-camera class="w-5 h-5" />
                    <span class="sr-only">Attach a photo</span>
                    <input type="file" accept="image/*" class="hidden" x-ref="imageInput" @change="attachImage($event)">
                </label>
                <input type="text" x-model="message" @keydown.enter="send()" @paste="pasteImage($event)"
                       :placeholder="imageThumb ? 'Say what to do with this photo...' : {{ \Illuminate\Support\Js::from(__('Ask a question or request an action...')) }}"
                       class="flex-1 min-w-0 bg-[#FAFAFA] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-[#3E2723] transition-all"
                       :disabled="streaming">
                <button @click="send()"
                        class="bg-[#3E2723] text-white p-3 rounded-xl hover:bg-[#271815] transition shadow-lg active:scale-90 disabled:opacity-50"
                        :disabled="streaming || (!message.trim() && !image)">
                    <x-lucide-send class="w-5 h-5" />
                </button>
            </div>
        </div>
    </div>

    <!-- Toggle Button Wrapper -->
    <div class="flex justify-end w-full">
        <button @click="toggle()"
                @pointerdown="if(!open) startDrag($event)"
                class="w-14 h-14 lg:w-16 lg:h-16 bg-[#3E2723] hover:bg-[#271815] text-white rounded-full shadow-2xl flex items-center justify-center transition-all hover:scale-110 active:scale-95 group relative"
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
                <x-lucide-bot class="w-7 h-7 lg:w-8 lg:h-8 group-hover:rotate-12 transition-transform" />
            </div>
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 rotate-45 scale-75"
                 x-transition:enter-end="opacity-100 rotate-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 rotate-0 scale-100"
                 x-transition:leave-end="opacity-0 -rotate-45 scale-75"
                 class="absolute inset-0 flex items-center justify-center">
                <x-lucide-chevron-down class="w-7 h-7 lg:w-8 lg:h-8" />
            </div>

            <!-- Notification Dot -->
            <div class="absolute -top-1 -right-1 w-5 h-5 bg-amber-500 border-4 border-[#FDF8F5] rounded-full"></div>
        </button>
    </div>
</div>
@endif
