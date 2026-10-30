@extends('layouts.admin')
@section('title', 'Barista AI Settings')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-4 sm:-m-6 lg:-m-8 p-4 sm:p-6 lg:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">

    <div class="max-w-7xl mx-auto">
        <div class="lk-page-head mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h2 class="flex items-center gap-3 text-[#3E2723]">
                    <span class="lk-brand text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                    <span class="text-lg md:text-xl font-bold tracking-wide uppercase mt-2">Barista AI Settings</span>
                </h2>
                <p class="lk-page-desc text-sm text-[#795548] mt-2 font-medium tracking-wide">
                    @if (auth()->user()->isSuperAdmin())
                        Barista AI tries {{ collect(\App\Services\AIService::PROVIDERS)->pluck('label')->join(', ', ', then ') }}, in that order, moving to the next one if a provider fails or runs out.
                        Every answer still gets its numbers from the shop's own data, whichever provider writes it.
                    @else
                        Barista AI uses a few online AI services and switches between them by itself. All you need here is the keys.
                    @endif
                </p>
            </div>
        </div>

        <div class="space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

            {{-- Counted by the system for every service (AiUsage); OpenRouter's credit is read from its account. --}}
            <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-amber-700">
                        <x-lucide-wallet class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">AI usage today</h3>
                        <p class="text-xs text-[#6D4C41] font-medium italic">Requests counted by the system for every service, plus what each service reports.</p>
                    </div>
                </div>
                <ul class="divide-y divide-[#F0E6D2]">
                    @foreach ($providers as $p => $provider)
                        @php $u = $usageToday[$p]; $q = $u['quota']; @endphp
                        <li class="py-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="flex items-center gap-2 min-w-0">
                                    <span class="w-2 h-2 rounded-full shrink-0 {{ $provider['configured'] ? 'bg-green-500' : 'bg-[#D7CCC8]' }}"></span>
                                    <span class="text-sm font-bold text-[#3E2723] truncate">{{ $provider['label'] }}</span>
                                </span>
                                @if (! $provider['configured'])
                                    <span class="text-sm text-[#795548]">not set up</span>
                                @elseif ($q)
                                    <span class="text-sm font-bold shrink-0 {{ $q['remaining'] === 0 ? 'text-red-700' : 'text-green-700' }}">{{ number_format($q['remaining']) }} left</span>
                                @endif
                            </div>
                            @if ($provider['configured'])
                                @if ($q)
                                    <div class="mt-2 ml-4 h-2 rounded-full bg-[#F0E6D2] overflow-hidden" title="{{ number_format($q['used']) }} of {{ number_format($q['limit']) }} used today ({{ $q['source'] }})">
                                        <div class="h-full rounded-full {{ $q['used'] >= $q['limit'] ? 'bg-red-500' : ($q['used'] >= 0.8 * $q['limit'] ? 'bg-amber-500' : 'bg-green-500') }}" style="width: {{ min(100, $q['limit'] ? round(100 * $q['used'] / $q['limit']) : 0) }}%"></div>
                                    </div>
                                @endif
                                <dl class="mt-2 ml-4 grid grid-cols-3 gap-2 text-xs">
                                    <div><dt class="text-[#795548]">Used today</dt><dd class="font-bold text-[#3E2723]">{{ number_format($q['used'] ?? $u['answered'] + $u['failed']) }}{{ $q ? ' of '.number_format($q['limit']) : '' }}</dd></div>
                                    <div><dt class="text-[#795548]">Answered · failed</dt><dd class="font-bold text-[#3E2723]">{{ number_format($u['answered']) }} · <span class="{{ $u['failed'] ? 'text-red-700' : '' }}">{{ number_format($u['failed']) }}</span></dd></div>
                                    <div><dt class="text-[#795548]">Last 7 days</dt><dd class="font-bold text-[#3E2723]">{{ number_format($u['week']) }}</dd></div>
                                </dl>
                                <p class="mt-1.5 ml-4 text-xs text-[#6D4C41]">
                                    @if ($p === 'openrouter' && $usage)
                                        Credit <span class="{{ $usage['balance'] < 0 ? 'text-red-700 font-bold' : '' }}">{{ $usage['balance'] < 0 ? '-' : '' }}${{ number_format(abs($usage['balance']), 2) }}</span> · {{ number_format($usage['free_used']) }} of {{ number_format($usage['free_limit']) }} free requests used today.
                                        @if ($usage['balance'] < 0)
                                            <span class="block text-red-800">The balance is below zero, so it's held to {{ number_format($usage['free_limit']) }} free requests a day. Add credit on openrouter.ai to raise it.</span>
                                        @endif
                                    @elseif ($p === 'openrouter')
                                        Couldn't reach OpenRouter just now to read its credit.
                                    @elseif ($q)
                                        Remaining is {{ $q['source'] }}.{{ $u['limits'] ? ' '.implode(' · ', array_diff($u['limits'], [number_format($q['remaining']).' of '.number_format($q['limit']).' requests left'])) : '' }}
                                    @elseif ($u['limits'])
                                        {{ implode(' · ', $u['limits']) }}
                                    @elseif ($p === 'groq')
                                        Groq reports what's left with each answer; it shows here after its next one.
                                    @elseif ($p === 'cloudflare')
                                        Free: 10,000 Neurons a day (see the Workers AI dashboard). {{ auth()->user()->isSuperAdmin() ? 'Set a daily request limit on its card to see what\'s left here.' : '' }}
                                    @else
                                        This service doesn't report what's left. {{ auth()->user()->isSuperAdmin() ? 'Set its daily limit on its card to track it here.' : '' }}
                                    @endif
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @php
                    $totalToday = collect($usageToday)->sum(fn ($u) => $u['answered'] + $u['failed']);
                    $totalWeek = collect($usageToday)->sum('week');
                @endphp
                <div class="mt-3 pt-3 border-t-2 border-[#F0E6D2] flex items-center justify-between text-sm">
                    <span class="font-bold text-[#3E2723]">All services</span>
                    <span class="text-[#3E2723]"><span class="font-bold">{{ number_format($totalToday) }}</span> today · <span class="font-bold">{{ number_format($totalWeek) }}</span> in 7 days</span>
                </div>
                <p class="mt-3 text-xs text-[#6D4C41]">Counts restart at midnight; most free allowances reset at 8:00 AM.</p>
            </div>

            {{-- API Keys — env vars always take priority; these are only used as a fallback. --}}
            <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-amber-700">
                        <x-lucide-key class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">API Keys</h3>
                        <p class="text-xs text-[#6D4C41] font-medium italic">Stored encrypted and never shown again. Leave a field blank to keep its saved key.</p>
                    </div>
                </div>

                <form action="{{ route('admin.settings.ai-providers.update') }}" method="POST" class="space-y-6" data-confirm="Save the AI keys?" data-confirm-text="Barista AI uses these keys from now on.">
                    @csrf
                    @foreach ($providers as $p => $provider)
                        @php $field = "{$p}_api_key"; @endphp
                        <div>
                            <label for="{{ $field }}" class="block text-xs font-bold text-[#3E2723] uppercase mb-2 tracking-wide">{{ $provider['label'] }} API Key</label>
                            <div x-data="{ show: false }" class="relative">
                                <input id="{{ $field }}" :type="show ? 'text' : 'password'" name="{{ $field }}" value="" class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl pl-4 pr-12 py-3 text-base sm:text-sm font-mono font-bold focus:outline-none focus:border-[#3E2723] transition-all" placeholder="{{ $provider['configured'] ? 'Saved — leave blank to keep' : 'Paste the key' }}" autocomplete="off">
                                <button type="button" @click="show = !show" :aria-label="show ? 'Hide API key' : 'Show API key'" class="absolute right-1 top-1/2 -translate-y-1/2 w-11 h-11 inline-flex items-center justify-center text-[#6D4C41] hover:text-[#3E2723]">
                                    <x-lucide-eye x-show="!show" class="w-4 h-4" />
                                    <x-lucide-eye-off x-show="show" class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                        @if ($p === 'cloudflare')
                            <div class="mt-3">
                                <label for="cloudflare_account_id" class="block text-xs font-bold text-[#3E2723] uppercase mb-2 tracking-wide">Cloudflare Account ID</label>
                                <input id="cloudflare_account_id" type="text" name="cloudflare_account_id" value="{{ \App\Services\AIService::cloudflareAccount() }}" autocomplete="off"
                                       class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-base sm:text-sm font-mono font-bold focus:outline-none focus:border-[#3E2723] transition-all" placeholder="From dash.cloudflare.com → AI → Workers AI">
                            </div>
                        @endif
                    @endforeach

                    <button type="submit" class="w-full py-4 bg-[#3E2723] hover:bg-[#271815] text-white rounded-2xl font-bold text-xs uppercase tracking-wide transition-all shadow-lg active:scale-[0.98] flex items-center justify-center gap-3">
                        <x-lucide-save class="w-5 h-5" />
                        <span>Save API Keys</span>
                    </button>
                </form>
            </div>
            </div>

            @if (auth()->user()->isSuperAdmin())
            @php $testable = collect($providers)->filter(fn ($p) => $p['configured'])->map(fn ($p) => array_column($p['models'], 'name')); @endphp
            <div x-data="lkModelTests()" class="space-y-6">
            @if ($testable->isNotEmpty())
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-[#6D4C41] font-medium">Tests run in the background, three at a time; keep using the system meanwhile. Each OpenRouter test uses one of its free requests for the day.</p>
                    <button type="button" @click="testAll(@js($testable))" :disabled="anyBusy()"
                            class="min-h-[44px] px-5 bg-[#3E2723] hover:bg-[#271815] text-white rounded-xl text-xs font-bold uppercase tracking-wide transition disabled:opacity-60 flex items-center gap-2">
                        <x-lucide-activity class="w-4 h-4" x-bind:class="anyBusy() ? 'animate-pulse' : ''" />
                        <span x-text="anyBusy() ? 'Testing…' : 'Test all models'">Test all models</span>
                    </button>
                </div>
            @endif
            {{-- Columns, not a grid: a long card doesn't leave holes beside it. --}}
            <div class="columns-1 lg:columns-2 2xl:columns-3 gap-6">
            @foreach ($providers as $key => $provider)
            <div x-data="{ showAll: false }" class="min-w-0 break-inside-avoid mb-6 bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-amber-700">
                            <x-lucide-bot class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">{{ $provider['label'] }}</h3>
                            @if ($provider['configured'])
                                <span class="text-xs text-green-700 font-bold uppercase tracking-wider">Configured</span>
                            @else
                                <span class="text-xs text-red-600 font-bold uppercase tracking-wider">Not configured</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        @if (! $provider['configured'])
                            {{-- No key, no health to report. --}}
                        @elseif ($provider['circuit']['open'])
                            <span class="flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full text-xs font-bold uppercase tracking-wide">
                                <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></span>
                                Cooling Down
                            </span>
                        @elseif ($provider['circuit']['failure_count'] > 0)
                            <span class="flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full text-xs font-bold uppercase tracking-wide">
                                {{ $provider['circuit']['failure_count'] }} recent failure(s)
                            </span>
                        @else
                            <span class="flex items-center gap-1.5 px-3 py-1 bg-green-50 text-green-700 border border-green-200 rounded-full text-xs font-bold uppercase tracking-wide">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                                Healthy
                            </span>
                        @endif
                        @if ($provider['configured'])
                        <button type="button" @click="test(@js($key), @js(array_column($provider['models'], 'name')))" :disabled="busy(@js($key))"
                                class="min-h-[44px] px-4 bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl text-xs font-bold uppercase tracking-wide whitespace-nowrap text-[#3E2723] hover:bg-white transition disabled:opacity-60 flex items-center gap-2">
                            <svg x-show="busy(@js($key))" x-cloak class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <span x-text="busy(@js($key)) ? 'Testing…' : 'Test models'">Test models</span>
                        </button>
                        @endif
                    </div>
                </div>

                @if ($provider['configured'] && $key !== 'openrouter')
                    <form action="{{ route('admin.settings.ai-providers.limit', $key) }}" method="POST" class="mb-3 flex items-center gap-2">
                        @csrf
                        <label for="limit-{{ $key }}" class="text-xs font-bold uppercase tracking-wide text-[#795548] shrink-0">Free requests a day</label>
                        <input id="limit-{{ $key }}" type="number" min="1" name="daily_limit" value="{{ \App\Models\Setting::get("ai_daily_limit_{$key}") }}" placeholder="{{ $key === 'groq' ? 'reported by Groq' : 'e.g. 1000' }}"
                               class="min-w-0 flex-1 min-h-[36px] bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-lg px-3 text-base sm:text-sm font-bold focus:outline-none focus:border-[#3E2723]">
                        <button type="submit" class="min-h-[36px] px-3 rounded-lg bg-[#FDF8F5] border-2 border-[#F0E6D2] text-xs font-bold uppercase tracking-wide text-[#3E2723] hover:bg-white">Save</button>
                    </form>
                @endif
                <div class="divide-y divide-[#F0E6D2]">
                    @foreach ($provider['models'] as $model)
                        <div class="py-2" x-data="{ editing: false, saving: false, isCustom: false, id: @js($key.'|'.$model['name']) }"
                             @if ($loop->index >= 5) x-show="showAll" x-cloak @endif
                             @if ($model['at']) title="Last checked {{ $model['at']->diffForHumans() }}" @endif>
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span x-show="!result(id)" class="w-2 h-2 rounded-full shrink-0 {{ match($model['status']) {
                                        'ok' => 'bg-green-500',
                                        'failed' => 'bg-red-500',
                                        default => 'bg-gray-300',
                                    } }}"></span>
                                    <span x-show="result(id)" x-cloak class="w-2 h-2 rounded-full shrink-0"
                                          x-bind:class="{ 'bg-amber-500 animate-ping': result(id)?.state === 'testing', 'bg-green-500': result(id)?.state === 'ok', 'bg-red-500': result(id)?.state === 'failed' }"></span>
                                    <span class="text-xs font-mono font-bold text-[#3E2723] truncate">{{ $model['name'] }}</span>
                                </div>
                                <div x-show="result(id)" x-cloak class="text-right shrink-0 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider"
                                     x-bind:class="{ 'text-amber-700': result(id)?.state === 'testing', 'text-green-700': result(id)?.state === 'ok', 'text-red-600': result(id)?.state === 'failed' }">
                                    <svg x-show="result(id)?.state === 'testing'" class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    <span x-text="result(id) ? label(result(id)) : ''"></span>
                                </div>
                                <div x-show="!result(id)" class="shrink-0 flex items-center gap-3">
                                    @if ($model['status'] === 'ok')
                                        <span class="text-xs font-bold text-green-700 uppercase tracking-wider">Healthy</span>
                                    @elseif ($model['status'] === 'failed')
                                        <span class="text-xs font-bold text-red-600 uppercase tracking-wider whitespace-nowrap">{{ $model['reason'] ? ucfirst(['http_429' => 'limit reached', 'http_404' => 'not available', 'http_400' => 'refused', 'http_500' => 'provider error', 'http_503' => 'provider busy', 'timed_out' => 'too slow', 'unauthorized' => 'bad key', 'forbidden' => 'not on your plan', 'exception' => 'no answer', 'empty_response' => 'empty reply'][$model['reason']] ?? str_replace('_', ' ', $model['reason'])) : 'Failed' }}</span>
                                    @else
                                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Never tested</span>
                                    @endif
                                    @if ($model['status'] === 'failed' && auth()->user()->isSuperAdmin())
                                        <button type="button" @click="editing = !editing" class="min-h-[32px] text-xs font-bold uppercase tracking-wide text-blue-600 hover:text-blue-800">
                                            Change
                                        </button>
                                    @endif
                                </div>
                            </div>

                            @if ($model['status'] === 'failed' && auth()->user()->isSuperAdmin())
                                @php
                                    // Every default/free model is offered EXCEPT the one currently
                                    // being replaced — suggesting a sibling that's already active
                                    // elsewhere in the list is still useful (better than an empty
                                    // dropdown), even if it means that model gets tried twice.
                                    $rowCatalog = array_values(array_diff($provider['catalog'], [$model['name']]));
                                    $rowMoreFree = array_values(array_diff($provider['more_free_models'], [$model['name']]));
                                @endphp
                                <form x-show="editing" x-cloak @submit="saving = true" action="{{ route('admin.settings.ai-providers.models.replace', $key) }}" method="POST" class="mt-3 flex items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="old_model" value="{{ $model['name'] }}">
                                    <select name="new_model" :disabled="isCustom" :required="!isCustom" @change="isCustom = ($event.target.value === '__custom__')" class="flex-1 bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-lg px-3 py-2 text-xs font-mono font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                                        <option value="">Choose a model...</option>
                                        @if (!empty($rowCatalog))
                                            <optgroup label="Suggested">
                                                @foreach ($rowCatalog as $catalogModel)
                                                    <option value="{{ $catalogModel }}">{{ $catalogModel }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                        @if (!empty($rowMoreFree))
                                            <optgroup label="Other Free Models (untested here)">
                                                @foreach ($rowMoreFree as $freeModel)
                                                    <option value="{{ $freeModel }}">{{ $freeModel }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                        <option value="__custom__">Other (type manually)...</option>
                                    </select>
                                    <input type="text" name="new_model" x-show="isCustom" x-cloak :disabled="!isCustom" :required="isCustom" placeholder="Type a model ID..." class="flex-1 bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-lg px-3 py-2 text-xs font-mono font-bold focus:outline-none focus:border-[#3E2723] transition-all">
                                    <button type="submit" :disabled="saving" class="px-3 py-2 bg-[#3E2723] hover:bg-[#271815] text-white rounded-lg text-xs font-bold uppercase tracking-wide transition disabled:opacity-60 disabled:cursor-not-allowed flex items-center gap-1.5 shrink-0">
                                        <svg x-show="saving" x-cloak class="animate-spin w-3 h-3" viewBox="0 0 24 24" fill="none">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        <span x-text="saving ? 'Saving...' : 'Save'">Save</span>
                                    </button>
                                    <button type="button" @click="editing = false; isCustom = false" class="px-3 py-2 bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-lg text-xs font-bold uppercase tracking-wide text-[#795548] hover:bg-white transition shrink-0">
                                        Cancel
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                    @if (count($provider['models']) > 5)
                        <button type="button" @click="showAll = !showAll"
                                class="w-full min-h-[44px] text-left text-xs font-bold uppercase tracking-wide text-[#6D4C41] hover:text-[#3E2723]"
                                x-text="showAll ? 'Show fewer' : @js('Show '.(count($provider['models']) - 5).' more')"></button>
                    @endif
                    {{-- Models added from a scan, shown at once (no refresh). --}}
                    <template x-for="m in (added[@js($key)] || [])" :key="m">
                        <div class="py-2 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-2 h-2 rounded-full shrink-0 bg-green-500"></span>
                                <span class="text-xs font-mono font-bold text-[#3E2723] truncate" x-text="m"></span>
                            </div>
                            <span class="text-xs font-bold text-green-700 uppercase tracking-wider shrink-0" x-text="label(result('check:' + @js($key) + '|' + m) || { state: 'added' }, true)"></span>
                        </div>
                    </template>
                </div>

                @if ($provider['configured'])
                <div class="mt-3 pt-3 border-t border-[#F0E6D2]">
                    <div class="flex flex-wrap items-center justify-between gap-x-4">
                    <button type="button" @click="scan(@js($key))" :disabled="scans[@js($key)]?.state === 'scanning'"
                            class="min-h-[44px] text-xs font-bold uppercase tracking-wide text-[#3E2723] hover:text-amber-700 transition disabled:opacity-60 flex items-center gap-2">
                        <span class="inline-flex" x-bind:class="scans[@js($key)]?.state === 'scanning' ? 'animate-spin' : ''"><x-lucide-radar class="w-4 h-4" /></span>
                        <span x-text="scans[@js($key)]?.state === 'scanning' ? 'Scanning…' : 'Scan for more models'">Scan for more models</span>
                    </button>
                    <form action="{{ route('admin.settings.ai-providers.models.reset', $key) }}" method="POST" data-confirm="Go back to the default models?" data-confirm-text="Models you added are removed from this provider.">
                        @csrf
                        <button type="submit" class="min-h-[44px] text-xs font-bold uppercase tracking-wide text-[#6D4C41] hover:text-[#3E2723] transition">Reset to defaults</button>
                    </form>
                    </div>
                    <template x-if="scans[@js($key)]?.state === 'done'">
                        <div class="mt-2" x-data="{ showFailed: false }">
                            <p class="text-xs text-[#6D4C41] font-medium" x-text="scans[@js($key)].message"></p>
                            <div class="divide-y divide-[#F0E6D2]">
                                <template x-for="m in scans[@js($key)].models.filter((m) => showFailed || result('check:' + @js($key) + '|' + m)?.state !== 'failed')" :key="m">
                                    <div class="py-2 flex items-center justify-between gap-3">
                                        <span class="text-xs font-mono font-bold text-[#3E2723] truncate min-w-0" x-text="m"></span>
                                        <div class="shrink-0 flex items-center gap-2 text-xs font-bold uppercase tracking-wider"
                                             x-bind:class="{ 'text-amber-700': result('check:' + @js($key) + '|' + m)?.state === 'testing', 'text-green-700': ['ok', 'added'].includes(result('check:' + @js($key) + '|' + m)?.state), 'text-red-600': result('check:' + @js($key) + '|' + m)?.state === 'failed' }">
                                            <svg x-show="result('check:' + @js($key) + '|' + m)?.state === 'testing'" class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24" fill="none">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                            </svg>
                                            <span x-text="label(result('check:' + @js($key) + '|' + m) || { state: 'queued' }, true)"></span>
                                            <button type="button" x-show="result('check:' + @js($key) + '|' + m)?.state === 'ok'" @click="add(@js($key), m)"
                                                    class="min-h-[36px] px-3 bg-[#3E2723] hover:bg-[#271815] text-white rounded-lg">Add</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <button type="button" x-show="failedCount(@js($key))" @click="showFailed = !showFailed"
                                    class="min-h-[44px] text-xs font-bold uppercase tracking-wide text-[#6D4C41] hover:text-[#3E2723] underline underline-offset-2"
                                    x-text="showFailed ? 'Hide the ones that failed' : 'Show ' + failedCount(@js($key)) + ' that failed'"></button>
                        </div>
                    </template>
                </div>
                @endif

            </div>
            @endforeach
            </div>
            </div>
            @else
                @php $connected = collect($providers)->filter(fn ($p) => $p['configured']); @endphp
                <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2] flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 {{ $connected->isNotEmpty() ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                        <x-lucide-bot class="w-6 h-6" />
                    </div>
                    <div>
                        @if ($connected->isNotEmpty())
                            <h3 class="text-sm font-bold text-[#3E2723] uppercase tracking-wide">Barista AI is connected</h3>
                            <p class="text-sm text-[#6D4C41] mt-1">Using {{ $connected->pluck('label')->join(', ', ' and ') }}. If one stops working, it moves to the next by itself.</p>
                        @else
                            <h3 class="text-sm font-bold text-red-700 uppercase tracking-wide">Barista AI isn't connected</h3>
                            <p class="text-sm text-[#6D4C41] mt-1">Add at least one key above.</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    // Per-model tests, each its own short request, so the page (and the shop) stays usable.
    window.lkModelTests = () => ({
        results: {},
        scans: {},
        added: {},
        queue: [],
        running: 0,
        url(name, provider) {
            return @js(route('admin.settings.ai-providers.models.test', '__P__')).replace('/__P__/models/test', '/' + provider + '/models/' + name);
        },
        post(name, provider, model) {
            return fetch(this.url(name, provider), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token()) },
                body: JSON.stringify({ model }),
            });
        },
        // Ask the provider what else this key can use, then qualify each one.
        async scan(provider) {
            this.scans[provider] = { state: 'scanning' };
            let res;
            try {
                const r = await fetch(this.url('scan', provider), { headers: { 'Accept': 'application/json' } });
                res = await r.json();
                if (!r.ok) throw new Error(res.message);
            } catch (e) {
                this.scans[provider] = { state: 'done', models: [], message: e.message || "Couldn't scan just now." };
                return;
            }
            const n = res.models.length;
            this.scans[provider] = { state: 'done', models: res.models, message: n
                ? n + ' more model' + (n > 1 ? 's' : '') + ' found. Each must call a tool to pass; only passing models can be added.'
                : 'No other models found for this key.' };
            res.models.forEach((model) => {
                this.results['check:' + provider + '|' + model] = { state: 'testing' };
                this.queue.push(['check', provider, model]);
            });
            this.pump();
        },
        // Added models move up into the provider's list; the scan list drops them.
        async add(provider, model) {
            const r = await this.post('add', provider, model);
            if (!r.ok) {
                this.results['check:' + provider + '|' + model] = { state: 'failed', reason: 'could_not_add' };
                return;
            }
            this.added[provider] = [...(this.added[provider] || []), model];
            this.scans[provider].models = this.scans[provider].models.filter((m) => m !== model);
        },
        testAll(byProvider) {
            Object.entries(byProvider).forEach(([provider, models]) => this.test(provider, models));
        },
        test(provider, models) {
            models.forEach((model) => {
                const id = provider + '|' + model;
                if (this.results[id]?.state === 'testing') return;
                this.results[id] = { state: 'testing' };
                this.queue.push(['test', provider, model]);
            });
            this.pump();
        },
        // Three at a time: each test can hold a server worker for up to 15 seconds.
        pump() {
            while (this.running < 3 && this.queue.length) {
                const [name, provider, model] = this.queue.shift();
                this.running++;
                this.post(name, provider, model)
                    .then((r) => (r.ok ? r.json() : { ok: false, reason: 'http_' + r.status }))
                    .catch(() => ({ ok: false, reason: 'no_connection' }))
                    .then((res) => {
                        this.results[(name === 'check' ? 'check:' : '') + provider + '|' + model] = { state: res.ok ? 'ok' : 'failed', ms: res.ms, reason: res.reason };
                        this.running--;
                        this.pump();
                    });
            }
        },
        result(id) { return this.results[id] || null; },
        busy(provider) { return Object.entries(this.results).some(([id, r]) => id.startsWith(provider + '|') && r.state === 'testing'); },
        anyBusy() { return Object.entries(this.results).some(([id, r]) => !id.startsWith('check:') && r.state === 'testing'); },
        failedCount(provider) {
            return Object.entries(this.results).filter(([id, r]) => id.startsWith('check:' + provider + '|') && r.state === 'failed').length;
        },
        label(r, candidate = false) {
            if (r.state === 'testing') return 'Testing…';
            if (r.state === 'added') return 'Added to the route';
            if (r.state === 'ok') return (candidate ? 'Passed · ' : 'Healthy · ') + (r.ms / 1000).toFixed(1) + ' s';
            const why = { no_tool_call: "didn't use the tool", timed_out: 'too slow', http_429: 'not on your plan or limit reached', http_404: 'not available' };
            return 'Failed — ' + (why[r.reason] || String(r.reason || 'error').replaceAll('_', ' '));
        },
    });
</script>
@endsection
