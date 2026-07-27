<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AIService
{
    protected $geminiKey;
    protected $groqKey;
    protected $openRouterKey;

    // --- FULL FREE MODEL STACK (MAY 2026) ---

    protected $geminiModels = [
        'gemini-2.0-flash',           // Latest Primary
        'gemini-1.5-pro',            // Pro Logic (Free Tier)
        'gemini-1.5-flash',          // Legacy High-Speed
    ];

    protected $groqModels = [
        'llama-3.3-70b-versatile',    // High-Tier General
        'deepseek-r1-distill-llama-70b', // Best Logic/Reasoning
        'qwen-qwq-32b',               // Strong Multilingual
        'mixtral-8x7b-32768',         // MoE Reliable
    ];

    protected $openRouterModels = [
        'openrouter/free',                       // Recommended Auto-Router
        'openai/gpt-oss-120b:free',               // Best Overall 2026
        'openai/gpt-oss-20b:free',                // Fast/Lightweight
        'google/gemini-2.0-flash:free',           // Long Context/Vision
        'meta-llama/llama-3.3-70b-instruct:free', // High Reasoning
        'nvidia/nemotron-3-super:free'            // Large MoE
    ];

    public function __construct()
    {
        $this->geminiKey = env('GEMINI_API_KEY') ?: \App\Models\Setting::get('gemini_api_key');
        $this->groqKey = env('GROQ_API_KEY') ?: \App\Models\Setting::get('groq_api_key');
        $this->openRouterKey = env('OPENROUTER_API_KEY') ?: \App\Models\Setting::get('ai_api_key');
    }

    public function getModel()
    {
        return 'Multi-Model Hyper-Stack (Google + Groq + OpenRouter)';
    }

    /**
     * Master resilient wrapper with recursive provider AND model fallback.
     *
     * @param array $tools Canonical tool definitions: [['name'=>..,'description'=>..,'parameters'=>jsonSchema], ...]
     *                      Passing tools to all 3 providers is intentional (per plan): whichever
     *                      provider actually answers is the one whose tool-calling gets used.
     * @param bool $fast Interactive-chat path: tighter per-request timeout and fewer candidate
     *                    models tried per provider before falling through. Non-interactive/cron
     *                    callers (forecasting, signal interpretation) should leave this false.
     */
    private function callAI($messages, $useVision = false, array $tools = [], bool $fast = false)
    {
        if ($this->geminiKey) {
            $response = $this->callGeminiLoop($messages, $useVision, $tools, $fast);
            if ($response) return $response;
        }

        if ($this->groqKey && !$useVision) {
            $response = $this->callGroqLoop($messages, $tools, $fast);
            if ($response) return $response;
        }

        if ($this->openRouterKey) {
            $response = $this->callOpenRouterLoop($messages, $tools, $fast);
            if ($response) return $response;
        }

        return null;
    }

    /**
     * Public tool-calling entry point used by ToolCallOrchestrator. Returns the
     * same normalized shape as callAI(): ['choices' => [['message' => ['content' => ?string, 'tool_calls' => [...]]]]]
     * A null return means every provider failed. $fast defaults true since every
     * current caller (guest/staff/admin chat, and the scheduled agent run) is either
     * interactive or already gated behind a cheap deterministic filter.
     */
    public function chatWithTools(array $messages, array $tools = [], bool $fast = true): ?array
    {
        return $this->callAI($messages, false, $tools, $fast);
    }

    /** @return array{timeout: int, modelLimit: int} */
    private function fastPathBudget(): array
    {
        return [
            'timeout' => (int) \App\Models\Setting::get('fast_path_timeout_seconds', 7),
            'modelLimit' => (int) \App\Models\Setting::get('fast_path_model_limit', 2),
        ];
    }

    private function callGeminiLoop($messages, $useVision, array $tools = [], bool $fast = false)
    {
        $models = $this->geminiModels;
        shuffle($models);
        $timeout = 15;
        if ($fast) {
            $budget = $this->fastPathBudget();
            $models = array_slice($models, 0, max(1, $budget['modelLimit']));
            $timeout = $budget['timeout'];
        }

        foreach ($models as $model) {
            try {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->geminiKey;
                $contents = $this->buildGeminiContents($messages);

                $payload = ['contents' => $contents];
                if (!empty($tools)) {
                    $payload['tools'] = $this->toGeminiTools($tools);
                }

                $response = Http::timeout($timeout)->post($url, $payload);
                if ($response->successful()) {
                    $normalized = $this->normalizeGeminiResponse($response->json());
                    $msg = $normalized['choices'][0]['message'];
                    if ($msg['content'] || !empty($msg['tool_calls'])) {
                        return $normalized;
                    }
                }
                if ($response->status() === 401) break;
            } catch (\Exception $e) { Log::error("Gemini Loop Exception ({$model}): " . $e->getMessage()); }
        }
        return null;
    }

    private function callGroqLoop($messages, array $tools = [], bool $fast = false)
    {
        $models = $this->groqModels;
        shuffle($models);
        $timeout = 10;
        if ($fast) {
            $budget = $this->fastPathBudget();
            $models = array_slice($models, 0, max(1, $budget['modelLimit']));
            $timeout = min($timeout, $budget['timeout']);
        }
        foreach ($models as $model) {
            try {
                $payload = ['model' => $model, 'messages' => $messages];
                if (!empty($tools)) {
                    $payload['tools'] = $this->toOpenAiTools($tools);
                }
                $response = Http::timeout($timeout)->withHeaders(['Authorization' => 'Bearer ' . $this->groqKey])->post('https://api.groq.com/openai/v1/chat/completions', $payload);
                if ($response->successful()) return $this->normalizeOpenAiResponse($response->json());
                if ($response->status() === 401) break;
            } catch (\Exception $e) { Log::error("Groq Loop Exception ({$model}): " . $e->getMessage()); }
        }
        return null;
    }

    private function callOpenRouterLoop($messages, array $tools = [], bool $fast = false)
    {
        $models = $this->openRouterModels;
        $first = array_shift($models);
        shuffle($models);
        array_unshift($models, $first);
        $timeout = 15;
        if ($fast) {
            $budget = $this->fastPathBudget();
            $models = array_slice($models, 0, max(1, $budget['modelLimit']));
            $timeout = $budget['timeout'];
        }
        foreach ($models as $model) {
            try {
                $payload = ['model' => $model, 'messages' => $messages];
                if (!empty($tools)) {
                    $payload['tools'] = $this->toOpenAiTools($tools);
                }
                $response = Http::timeout($timeout)->withHeaders(['Authorization' => 'Bearer ' . $this->openRouterKey, 'HTTP-Referer' => config('app.url'), 'X-Title' => config('app.name')])->post('https://openrouter.ai/api/v1/chat/completions', $payload);
                if ($response->successful()) return $this->normalizeOpenAiResponse($response->json());
                if ($response->status() === 401) break;
            } catch (\Exception $e) { Log::error("OpenRouter Loop Exception ({$model}): " . $e->getMessage()); }
        }
        return null;
    }

    /**
     * Build Gemini's `contents` array from canonical messages, including the
     * two tool-calling-specific turn shapes: an assistant message carrying
     * tool_calls (encoded as functionCall parts) and a tool-result message
     * (encoded as a functionResponse part).
     */
    private function buildGeminiContents(array $messages): array
    {
        $contents = [];
        foreach ($messages as $msg) {
            if (($msg['role'] ?? null) === 'tool') {
                $contents[] = [
                    'role' => 'function',
                    'parts' => [[
                        'functionResponse' => [
                            'name' => $msg['name'] ?? 'tool',
                            'response' => ['result' => $msg['content']],
                        ],
                    ]],
                ];
                continue;
            }

            if (($msg['role'] ?? null) === 'assistant' && !empty($msg['tool_calls'])) {
                $contents[] = [
                    'role' => 'model',
                    'parts' => array_map(fn ($tc) => [
                        'functionCall' => ['name' => $tc['name'], 'args' => $tc['arguments']],
                    ], $msg['tool_calls']),
                ];
                continue;
            }

            $role = ($msg['role'] === 'user' || $msg['role'] === 'system') ? 'user' : 'model';
            $parts = [];
            if (is_array($msg['content'])) {
                foreach ($msg['content'] as $p) {
                    if ($p['type'] === 'text') $parts[] = ['text' => $p['text']];
                    elseif ($p['type'] === 'image_url' && preg_match('/data:image\/.*;base64,(.*)/', $p['image_url']['url'], $m)) {
                        $parts[] = ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $m[1]]];
                    }
                }
            } else { $parts[] = ['text' => $msg['content']]; }
            $contents[] = ['role' => $role, 'parts' => $parts];
        }
        return $contents;
    }

    /** Canonical tool defs -> Gemini's tools/functionDeclarations shape. */
    private function toGeminiTools(array $tools): array
    {
        return [[
            'functionDeclarations' => array_map(fn ($t) => [
                'name' => $t['name'],
                'description' => $t['description'],
                'parameters' => $t['parameters'],
            ], $tools),
        ]];
    }

    /** Canonical tool defs -> OpenAI-compatible (Groq/OpenRouter) tools shape. */
    private function toOpenAiTools(array $tools): array
    {
        return array_map(fn ($t) => [
            'type' => 'function',
            'function' => [
                'name' => $t['name'],
                'description' => $t['description'],
                'parameters' => $t['parameters'],
            ],
        ], $tools);
    }

    /** Gemini's raw generateContent response -> canonical ['choices'=>[['message'=>['content'=>?,'tool_calls'=>[]]]]]. */
    private function normalizeGeminiResponse(array $data): array
    {
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $text = null;
        $toolCalls = [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text = ($text ?? '') . $part['text'];
            }
            if (isset($part['functionCall'])) {
                $toolCalls[] = [
                    'id' => 'call_' . Str::random(8),
                    'name' => $part['functionCall']['name'] ?? '',
                    'arguments' => $part['functionCall']['args'] ?? [],
                ];
            }
        }
        return ['choices' => [['message' => ['content' => $text, 'tool_calls' => $toolCalls]]]];
    }

    /** Groq/OpenRouter's raw OpenAI-compatible response -> canonical shape (decodes JSON-string tool arguments). */
    private function normalizeOpenAiResponse(array $data): array
    {
        $message = $data['choices'][0]['message'] ?? [];
        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $call) {
            $toolCalls[] = [
                'id' => $call['id'] ?? ('call_' . Str::random(8)),
                'name' => $call['function']['name'] ?? '',
                'arguments' => json_decode($call['function']['arguments'] ?? '{}', true) ?? [],
            ];
        }
        return ['choices' => [['message' => ['content' => $message['content'] ?? null, 'tool_calls' => $toolCalls]]]];
    }

    /** Shared by chat() and ToolCallOrchestrator (guest audience). */
    public function buildGuestSystemPrompt(): string
    {
        return "CORE IDENTITY:\nYou are Barista AI for Lawa't Kape.\n\nSTRICT DATA RULES:\n1. ONLY use provided data.\n2. DO NOT hallucinate ingredients or prices.\n3. If unknown, say so.\n\nKNOWLEDGE BASE:\n- Best Sellers: " . ($this->getBestSellersContext() ?: "Available at counter") . "\n- Wi-Fi Pricing:\n" . $this->getPricingContext() . "\n- Menu:\n" . $this->getMenuContext();
    }

    /** Shared by adminChat() and ToolCallOrchestrator (admin audience). */
    public function buildAdminSystemPrompt(): string
    {
        $lowStockThreshold = (int) \App\Models\Setting::get('low_stock_threshold', 500);
        $lowStockIngredients = \App\Models\Ingredient::where('current_stock', '<', $lowStockThreshold)->get(['name', 'current_stock', 'unit'])->map(fn($i) => "{$i->name} ({$i->current_stock}{$i->unit})")->toArray();
        $todaysSales = \App\Models\Sale::where('status', 'completed')->where('created_at', '>=', Carbon::today())->sum('total_amount');
        $activeVouchers = \App\Models\Voucher::where('is_used', false)->count();

        return "CORE IDENTITY:
You are Barista AI, a powerful executive assistant and business analyst for the owner of Lawa't Kape.

YOUR MISSION:
Your goal is to help the owner manage the business with clinical precision. Be proactive, professional, and data-driven. When a tool is available that would accomplish what the owner is asking for, use it rather than just describing what they should do.

CURRENT SHOP STATUS:
- Today's Revenue: PHP " . number_format($todaysSales, 2) . "
- Active Wi-Fi Vouchers: " . $activeVouchers . "
- Low Stock Alerts: " . (empty($lowStockIngredients) ? 'None' : implode(', ', $lowStockIngredients)) . "
- Best Selling Items: " . ($this->getBestSellersContext() ?: "N/A") . "
- Predictions: " . (Cache::get('barista_forecast_deep')['trend_analysis'] ?? 'N/A') . "

MENU & RECIPES:
" . $this->getMenuContext() . "

OPERATIONAL GUIDELINES:
1. Act as a trusted consultant. If you see low stock, suggest ordering. If sales are down, suggest a promotion.
2. Be concise but highly insightful.
3. Your loyalty is to the owner/admin. Help them optimize every corner of the kape.";
    }

    /** Shared by staffChat() and ToolCallOrchestrator (staff audience). */
    public function buildStaffSystemPrompt(): string
    {
        return "You are Barista Support for staff. Menu & Recipes:\n" . $this->getMenuContext() . "\nSTRICT RULE: Do not guess recipes if not listed above. If a tool is available to do what's being asked (e.g. checking stock, restocking, voiding a sale), use it.";
    }

    public function chat($message, $history = [])
    {
        $messages = [['role' => 'system', 'content' => $this->buildGuestSystemPrompt()]];
        foreach ($history as $msg) { $messages[] = ['role' => $msg['role'], 'content' => $msg['content']]; }
        $messages[] = ['role' => 'user', 'content' => $message];

        $data = $this->callAI($messages, false, [], true);
        return $data['choices'][0]['message']['content'] ?? $this->localFallback($message);
    }

    public function adminChat($message, $history = [])
    {
        $messages = [['role' => 'system', 'content' => $this->buildAdminSystemPrompt()]];
        foreach ($history as $msg) { $messages[] = ['role' => $msg['role'], 'content' => $msg['content']]; }
        $messages[] = ['role' => 'user', 'content' => $message];

        $data = $this->callAI($messages, false, [], true);
        return $data['choices'][0]['message']['content'] ?? "☕ I'm having trouble connecting to our business intelligence stack right now.";
    }

    public function staffChat($message, $history = [])
    {
        $messages = [['role' => 'system', 'content' => $this->buildStaffSystemPrompt()]];
        foreach ($history as $msg) { $messages[] = ['role' => $msg['role'], 'content' => $msg['content']]; }
        $messages[] = ['role' => 'user', 'content' => $message];

        $data = $this->callAI($messages, false, [], true);
        return $data['choices'][0]['message']['content'] ?? "☕ Staff AI stack offline.";
    }

    public function analyzeSalesTrends($historicalSales, $productPerformance, $wastageData = [], $daysOfData = 0, $recentPerformance = [])
    {
        $prompt = "You are a business data analyst for Lawa't Kape. Analyze these metrics and provide a 7-day forecast.

        ### SYSTEM DATA ###
        - Historical Sales (Daily Totals): " . json_encode($historicalSales) . "
        - Product Performance (30 Days): " . json_encode($productPerformance) . "
        - Recent Performance (72 Hours): " . json_encode($recentPerformance) . "
        - Wastage Logs: " . json_encode($wastageData) . "

        ### OPERATIONAL RULES ###
        1. Daily Forecast: DO NOT use flat averages. Assume typical cafe seasonality (Weekends 2x-3x higher than weekdays).
        2. Strategic Advice: Provide exactly ONE actionable tip (max 25 words). No lists.
        3. Demand Risk: If 0 sold in 72h, flag it with specific reason.

        Return ONLY valid JSON:
        {
            \"forecast_total\": float,
            \"daily_forecast\": [{\"day\": \"string (Mon, Tue)\", \"amount\": float}],
            \"trend_analysis\": \"string (max 15 words)\",
            \"predicted_top_products\": [\"string\"],
            \"predicted_low_products\": [\"string\"],
            \"demand_risk_alerts\": [{\"item\": \"string\", \"reason\": \"string\", \"severity\": \"warning|danger\"}],
            \"strategic_advice\": \"string (max 25 words)\",
            \"context_tags\": [\"string\"]
        }";

        $messages = [['role' => 'user', 'content' => $prompt]];
        $data = $this->callAI($messages);

        if ($data) {
            $raw = str_replace(['```json', '```'], '', trim($data['choices'][0]['message']['content']));
            return json_decode($raw, true);
        }
        return null;
    }

    /**
     * Turn deterministically-detected cross-domain signals (see
     * CrossDomainCorrelationService) into a short human-readable narrative
     * for the admin notification. Does NOT decide actions — RunAgentAnalysis
     * separately drives ToolCallOrchestrator for that, so action selection
     * goes through the normal tool-calling/permission/audit pipeline.
     */
    public function interpretSignals(array $signals): ?array
    {
        $prompt = "You are Barista AI reviewing automatically-detected operational signals for Lawa't Kape (a POS + Wi-Fi captive portal system).

        ### SIGNALS ###
        " . json_encode($signals) . "

        Summarize these signals for the owner in one short narrative. Do not invent signals not listed above.

        Return ONLY valid JSON:
        {
            \"narrative\": \"string (max 40 words, plain language summary of what was found)\",
            \"context_tags\": [\"string\"]
        }";

        $messages = [['role' => 'user', 'content' => $prompt]];
        $data = $this->callAI($messages);

        if ($data) {
            $raw = str_replace(['```json', '```'], '', trim($data['choices'][0]['message']['content']));
            return json_decode($raw, true);
        }
        return null;
    }

    public function extractPaymentDetails($input)
    {
        $isText = !file_exists(storage_path('app/' . $input)) || (strlen($input) > 255);
        $messages = [];
        if ($isText) {
            $messages[] = [
                'role' => 'user',
                'content' => 'Extract GCash Ref Number and Amount from this email. Return ONLY JSON: {"reference_number": "string", "amount": float}. Text:' . "\n" . $input
            ];
        } else {
            $imageData = base64_encode(file_get_contents(storage_path('app/' . $input)));
            $messages[] = [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Extract GCash Reference Number. Return ONLY JSON: {"reference_number": "string"}'],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,' . $imageData]]
                ]
            ];
        }
        $data = $this->callAI($messages, !$isText);
        if ($data) {
            $raw = str_replace(['```json', '```'], '', trim($data['choices'][0]['message']['content']));
            return json_decode($raw, true);
        }
        return null;
    }

    /** Short-TTL cache: these were previously re-queried on every single chat call. */
    private function getPricingContext() {
        return Cache::remember('ai_ctx_pricing', 300, function () {
            $durations = json_decode(\App\Models\Setting::get('voucher_durations', '{"20":60,"50":180,"100":1440}'), true);
            $pricing = ""; if ($durations) { ksort($durations); foreach ($durations as $p => $m) { $pricing .= "- PHP {$p} for " . ($m >= 60 ? round($m/60) . "h" : "{$m}m") . "\n"; } }
            return $pricing;
        });
    }

    private function getMenuContext() {
        return Cache::remember('ai_ctx_menu', 300, function () {
            $products = \App\Models\Product::where('status', 'Active')->with('ingredients')->get();
            $ctx = "";
            foreach ($products->groupBy('category') as $cat => $items) {
                $ctx .= "Category: {$cat}\n";
                foreach ($items as $i) {
                    $ctx .= "- {$i->name}: PHP " . number_format($i->price, 2);
                    if ($i->ingredients->isNotEmpty()) { $ctx .= " (Official Ingredients: " . $i->ingredients->pluck('name')->implode(', ') . ")"; }
                    $ctx .= "\n";
                }
            }
            return $ctx;
        });
    }

    private function getBestSellersContext() {
        return Cache::remember('ai_ctx_best_sellers', 300, function () {
            $bestSellers = \App\Models\SaleItem::whereHas('sale', fn($q) => $q->where('status', 'completed'))->select('item_name', DB::raw('SUM(quantity) as total_qty'))->where('created_at', '>=', Carbon::now()->subDays(30))->groupBy('item_name')->orderByDesc('total_qty')->take(3)->pluck('item_name')->toArray();
            return !empty($bestSellers) ? implode(', ', $bestSellers) : null;
        });
    }

    private function localFallback($message) {
        $lowerMsg = strtolower($message); $list = $this->getBestSellersContext() ?: "Tapsilog and Spanish Latte";
        if (str_contains($lowerMsg, 'hi') || str_contains($lowerMsg, 'hello')) return "Hey! ☕ Barista AI here. I'm busy, but can help with Wi-Fi, menu, or suggest {$list}!";
        if (str_contains($lowerMsg, 'best') || str_contains($lowerMsg, 'recommend')) return "☕ Best-sellers: {$list}!";
        if (str_contains($lowerMsg, 'wifi')) return "📶 Visit http://neverssl.com to force the portal login.";
        return "☕ Serving guests! Check Menu or Wi-Fi tabs!";
    }
}
