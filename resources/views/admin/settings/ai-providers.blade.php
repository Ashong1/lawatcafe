@extends('layouts.admin')
@section('title', 'AI Providers')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">

    <div class="max-w-6xl mx-auto">
        <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h2 class="flex items-center gap-3 text-[#3E2723]">
                    <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                    <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">AI Providers</span>
                </h2>
                <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Barista AI tries these providers in order (Gemini &rarr; Groq &rarr; OpenRouter). Status reflects real recent usage, or click "Test Now" to check right now.</p>
            </div>
        </div>

        <div class="max-w-3xl mx-auto space-y-6">

            {{-- API Keys — env vars always take priority; these are only used as a fallback. --}}
            <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-amber-700">
                        <x-lucide-key class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-[#3E2723] uppercase tracking-widest">API Keys</h3>
                        <p class="text-[10px] text-[#A1887F] font-medium italic">Used only if the matching env var (GEMINI_API_KEY / GROQ_API_KEY / OPENROUTER_API_KEY) isn't already set on the server.</p>
                    </div>
                </div>

                <form action="{{ route('admin.settings.ai-providers.update') }}" method="POST" class="space-y-6">
                    @csrf
                    @foreach (['gemini_api_key' => 'Gemini API Key', 'groq_api_key' => 'Groq API Key', 'openrouter_api_key' => 'OpenRouter API Key'] as $field => $label)
                        <div>
                            <label class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest">{{ $label }}</label>
                            <div x-data="{ show: false }" class="relative">
                                <input :type="show ? 'text' : 'password'" name="{{ $field }}" value="{{ $settings[$field] }}" class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-mono font-bold focus:outline-none focus:border-[#3E2723] transition-all" placeholder="Enter API Key...">
                                <button type="button" @click="show = !show" class="absolute right-4 top-1/2 -translate-y-1/2 text-[#A1887F] hover:text-[#3E2723]">
                                    <x-lucide-eye x-show="!show" class="w-4 h-4" />
                                    <x-lucide-eye-off x-show="show" class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    @endforeach

                    <button type="submit" class="w-full py-4 bg-[#3E2723] hover:bg-[#271815] text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all shadow-lg active:scale-[0.98] flex items-center justify-center gap-3">
                        <x-lucide-save class="w-5 h-5" />
                        <span>Save API Keys</span>
                    </button>
                </form>
            </div>

            @foreach ($providers as $key => $provider)
            <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-amber-700">
                            <x-lucide-bot class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-[#3E2723] uppercase tracking-widest">{{ $provider['label'] }}</h3>
                            @if ($provider['configured'])
                                <span class="text-[10px] text-green-700 font-bold uppercase tracking-wider">Configured</span>
                            @else
                                <span class="text-[10px] text-red-600 font-bold uppercase tracking-wider">Not configured</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        @if ($provider['circuit']['open'])
                            <span class="flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full text-[9px] font-black uppercase tracking-widest">
                                <span class="w-1.5 h-1.5 bg-amber-500 rounded-full animate-pulse"></span>
                                Cooling Down
                            </span>
                        @elseif ($provider['circuit']['failure_count'] > 0)
                            <span class="flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-full text-[9px] font-black uppercase tracking-widest">
                                {{ $provider['circuit']['failure_count'] }} recent failure(s)
                            </span>
                        @else
                            <span class="flex items-center gap-1.5 px-3 py-1 bg-green-50 text-green-700 border border-green-200 rounded-full text-[9px] font-black uppercase tracking-widest">
                                <span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>
                                Healthy
                            </span>
                        @endif

                        <form action="{{ route('admin.settings.ai-providers.test', $key) }}" method="POST">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl text-[10px] font-black uppercase tracking-widest text-[#3E2723] hover:bg-white transition">
                                Test Now
                            </button>
                        </form>
                    </div>
                </div>

                <div class="divide-y divide-[#F0E6D2]">
                    @foreach ($provider['models'] as $model)
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-2 h-2 rounded-full shrink-0 {{ match($model['status']) {
                                    'ok' => 'bg-green-500',
                                    'failed' => 'bg-red-500',
                                    default => 'bg-gray-300',
                                } }}"></span>
                                <span class="text-xs font-mono font-bold text-[#3E2723] truncate">{{ $model['name'] }}</span>
                            </div>
                            <div class="text-right shrink-0">
                                @if ($model['status'] === 'ok')
                                    <span class="text-[10px] font-bold text-green-700 uppercase tracking-wider">Healthy</span>
                                @elseif ($model['status'] === 'failed')
                                    <span class="text-[10px] font-bold text-red-600 uppercase tracking-wider">Failed{{ $model['reason'] ? ' — ' . $model['reason'] : '' }}</span>
                                @else
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Never tested</span>
                                @endif
                                @if ($model['at'])
                                    <span class="block text-[9px] text-[#A1887F]">{{ $model['at']->diffForHumans() }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
