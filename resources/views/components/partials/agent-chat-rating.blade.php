{{-- Rating row under an assistant reply. Included from both chat modes, so it
     relies only on the surrounding Alpine scope (msg, index) rather than props.

     Shown for real answers only — never under the opening greeting, which nobody
     can meaningfully rate and which would otherwise flood the corpus with
     ratings of a hardcoded string.

     No x-cloak: this starts visible and only ever swaps state. Cloaking it would
     hide the control until Alpine boots, on the one surface (the guest portal)
     where the slowest devices live. --}}
<template x-if="msg.kind === 'text' && msg.role === 'assistant' && !msg.isGreeting">
    <div class="flex items-center gap-1.5 mt-1 mx-1">
        <template x-if="rated[index] === undefined">
            <div class="flex items-center gap-1.5">
                <button type="button" x-on:click="rate(index, 1)" aria-label="This answer was helpful"
                        class="p-1 rounded-lg text-[#B0A69C] hover:text-green-600 hover:bg-green-50 transition">
                    <x-lucide-thumbs-up class="w-3.5 h-3.5" />
                </button>
                <button type="button" x-on:click="rate(index, -1)" aria-label="This answer was not helpful"
                        class="p-1 rounded-lg text-[#B0A69C] hover:text-red-600 hover:bg-red-50 transition">
                    <x-lucide-thumbs-down class="w-3.5 h-3.5" />
                </button>
            </div>
        </template>

        <template x-if="rated[index] !== undefined">
            <span class="text-[9px] font-black uppercase tracking-widest text-[#B0A69C]"
                  x-text="rated[index] === 1 ? 'Thanks!' : 'Noted — thanks'"></span>
        </template>

        {{-- Corrections are the strongest signal there is, so they are limited to
             signed-in staff/admin. Accepting them from an anonymous guest would
             hand the assistant's future behaviour to whoever is on the WiFi. --}}
        @if($csrf)
            <template x-if="rated[index] === -1 && correcting !== index && !corrected[index]">
                <button type="button" x-on:click="correcting = index"
                        class="text-[9px] font-black uppercase tracking-widest text-amber-700 hover:text-amber-900 underline decoration-dotted transition">
                    Teach it
                </button>
            </template>
            <template x-if="corrected[index]">
                <span class="text-[9px] font-black uppercase tracking-widest text-green-700">Correction saved</span>
            </template>
        @endif
    </div>
</template>

@if($csrf)
    <template x-if="correcting === index">
        <div class="mt-2 mx-1 p-3 rounded-xl bg-amber-50 border border-amber-200">
            <label class="block text-[9px] font-black uppercase tracking-widest text-amber-800 mb-1.5"
                   x-bind:for="'correction-' + index">What should it have said?</label>
            <textarea x-bind:id="'correction-' + index" x-model="correctionText" rows="2"
                      class="w-full text-xs font-medium bg-white border border-amber-200 rounded-lg p-2 focus:outline-none focus:border-amber-500 transition"
                      placeholder="The answer you would have given"></textarea>
            <div class="flex gap-2 mt-2">
                <button type="button" x-on:click="submitCorrection(index)" x-bind:disabled="!correctionText.trim()"
                        class="px-3 py-1.5 bg-[#3E2723] hover:bg-[#271815] disabled:opacity-40 text-white rounded-lg text-[9px] font-black uppercase tracking-widest transition">Save</button>
                <button type="button" x-on:click="correcting = null; correctionText = ''"
                        class="px-3 py-1.5 bg-white border border-[#E6D5C3] text-[#6D4C41] rounded-lg text-[9px] font-black uppercase tracking-widest transition">Cancel</button>
            </div>
        </div>
    </template>
@endif
