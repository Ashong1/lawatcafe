@extends('layouts.admin')
@section('title', 'API Integrations')

@section('content')
<div class="bg-[#FDF8F5] min-h-screen -m-6 p-6 md:p-8 text-[#4A3B32]" style="font-family: 'Montserrat', sans-serif;">
    
    <div class="max-w-6xl mx-auto">
        <div class="mb-8 border-b border-[#E6D5C3] pb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h2 class="flex items-center gap-3 text-[#3E2723]">
                    <span class="text-3xl md:text-4xl tracking-wide font-bold pr-1" style="font-family: 'Dancing Script', cursive;">Lawa't</span>
                    <span class="text-lg md:text-xl font-bold tracking-[0.2em] uppercase mt-2">API Integrations</span>
                </h2>
                <p class="text-sm text-[#8D6E63] mt-2 font-medium tracking-wide">Manage external services like automated payment monitoring and AI engines.</p>
            </div>
        </div>

        <form action="{{ route('admin.settings.integrations.update') }}" method="POST">
            @csrf
            <div class="max-w-2xl mx-auto space-y-8">
                
                {{-- Barista AI Configuration --}}
                <div class="bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-[#F0E6D2]">
                    <div class="flex items-center justify-between mb-8">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center text-amber-700">
                                <x-lucide-bot class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-[#3E2723] uppercase tracking-widest">Barista AI Engine</h3>
                                <p class="text-[10px] text-[#A1887F] font-medium italic">Configure your active business intelligence model.</p>
                            </div>
                        </div>
                        <span class="flex items-center gap-1.5 px-3 py-1 bg-green-50 text-green-700 border border-green-200 rounded-full text-[9px] font-black uppercase tracking-widest">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                            Online
                        </span>
                    </div>

                    <div class="space-y-6">
                        <div>
                            <label class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest">Active AI Model</label>
                            <div class="relative">
                                <select name="active_ai_model" class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-bold focus:outline-none focus:border-[#3E2723] transition-all appearance-none cursor-pointer">
                                    <option value="gemini-1.5-pro" {{ $settings['active_ai_model'] === 'gemini-1.5-pro' ? 'selected' : '' }}>Gemini 1.5 Pro (Best for Complex Parsing)</option>
                                    <option value="gemini-1.5-flash" {{ $settings['active_ai_model'] === 'gemini-1.5-flash' ? 'selected' : '' }}>Gemini 1.5 Flash (Fastest Response Time)</option>
                                    <option value="llama-3-groq" {{ $settings['active_ai_model'] === 'llama-3-groq' ? 'selected' : '' }}>Llama 3 70B (Via Groq API)</option>
                                    <option value="claude-3-haiku" {{ $settings['active_ai_model'] === 'claude-3-haiku' ? 'selected' : '' }}>Claude 3 Haiku (Free Tier)</option>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-[#A1887F]">
                                    <x-lucide-chevron-down class="w-4 h-4" />
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-[#3E2723] uppercase mb-2 tracking-widest">Model API Key</label>
                            <div x-data="{ show: false }" class="relative">
                                <input :type="show ? 'text' : 'password'" name="ai_api_key" value="{{ $settings['ai_api_key'] }}" class="w-full bg-[#FDF8F5] border-2 border-[#F0E6D2] rounded-xl px-4 py-3 text-sm font-mono font-bold focus:outline-none focus:border-[#3E2723] transition-all" placeholder="Enter API Key...">
                                <button type="button" @click="show = !show" class="absolute right-4 top-1/2 -translate-y-1/2 text-[#A1887F] hover:text-[#3E2723]">
                                    <x-lucide-eye x-show="!show" class="w-4 h-4" />
                                    <x-lucide-eye-off x-show="show" class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <p class="text-[10px] text-[#8D6E63] mt-8 italic leading-relaxed border-t border-[#F0E6D2] pt-4">
                        The AI engine analyzes sales trends and provides business intelligence feedback via the floating chat interface. It also extracts details from GCash payment receipt screenshots uploaded on the WiFi portal.
                    </p>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full py-5 bg-[#3E2723] hover:bg-[#271815] text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] transition-all shadow-xl shadow-amber-900/20 active:scale-[0.98] flex items-center justify-center gap-3">
                        <x-lucide-save class="w-5 h-5" />
                        <span>Update Integrations</span>
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>
@endsection
