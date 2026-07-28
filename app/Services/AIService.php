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
    /** Total-request ceiling for streaming calls — see streamGeminiLoop()/streamOpenAiCompatibleLoop(). */
    private const STREAM_TIMEOUT = 18;

    protected $geminiKey;
    protected $groqKey;
    protected $openRouterKey;

    // --- FULL FREE MODEL STACK (MAY 2026) ---

    protected $geminiModels = [
        'gemini-2.0-flash',           // Latest Primary
        'gemini-1.5-pro',            // Pro Logic (Free Tier)
        'gemini-1.5-flash',          // Legacy High-Speed
    ];

    // Verified 2026-07-27 against the real Groq /models endpoint and each
    // model's actual chat-completions + tool-calling behavior (not just
    // listed-as-available) — every entry below confirmed working for both.
    // Excluded on purpose despite being live: openai/gpt-oss-safeguard-20b
    // (a content-moderation classifier, not a general chat model),
    // qwen/qwen3.6-27b (leaks raw <think>...</think> reasoning into the
    // reply text — needs stripping before it's safe to show a user),
    // groq/compound & groq/compound-mini (Groq's own agentic meta-model with
    // its own built-in tools, which could conflict with our function-calling
    // schema), allam-2-7b (Arabic-focused, not relevant here).
    protected $groqModels = [
        'llama-3.3-70b-versatile',
        'llama-3.1-8b-instant',
        'openai/gpt-oss-120b',
        'openai/gpt-oss-20b',
    ];

    // Verified 2026-07-27 against OpenRouter's real /models pricing data
    // (pricing.prompt === pricing.completion === "0", not just a ":free"
    // suffix) AND each model's actual chat-completions + tool-calling
    // behavior. Excluded: nvidia/nemotron-3.5-content-safety:free (a safety
    // classifier, not chat) and google/lyria-3-*:free (music generation, not
    // chat) — both free but not usable here. A few other free entries
    // (poolside/laguna-s-2.1:free, google/gemma-4-31b-it:free,
    // nvidia/nemotron-3-ultra-550b-a55b:free, poolside/laguna-m.1:free,
    // nvidia/nemotron-nano-12b-v2-vl:free) were 429/timing-out at test time —
    // may be worth retrying later, left out for now rather than risk
    // wasting a fail-over slot on a currently-flaky model.
    protected $openRouterModels = [
        'openrouter/free',
        'openai/gpt-oss-20b:free',
        'inclusionai/ling-3.0-flash:free',
        'poolside/laguna-xs-2.1:free',
        'cohere/north-mini-code:free',
        'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free',
        'google/gemma-4-26b-a4b-it:free',
        'nvidia/nemotron-3-super-120b-a12b:free',
        'nvidia/nemotron-3-nano-30b-a3b:free',
        'nvidia/nemotron-nano-9b-v2:free',
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
        if ($this->geminiKey && !$this->providerIsOpen('gemini')) {
            $response = $this->callGeminiLoop($messages, $useVision, $tools, $fast);
            $this->recordProviderResult('gemini', (bool) $response);
            if ($response) return $response;
        }

        if ($this->groqKey && !$useVision && !$this->providerIsOpen('groq')) {
            $response = $this->callGroqLoop($messages, $tools, $fast);
            $this->recordProviderResult('groq', (bool) $response);
            if ($response) return $response;
        }

        if ($this->openRouterKey && !$this->providerIsOpen('openrouter')) {
            $response = $this->callOpenRouterLoop($messages, $tools, $fast);
            $this->recordProviderResult('openrouter', (bool) $response);
            if ($response) return $response;
        }

        return null;
    }

    /**
     * Circuit breaker: a provider that's failed repeatedly in the last few
     * minutes gets skipped entirely (cascade falls straight to the next
     * provider) rather than retried at full cost on every single call.
     */
    private function providerIsOpen(string $provider): bool
    {
        return Cache::has("ai_circuit_open_{$provider}");
    }

    private function recordProviderResult(string $provider, bool $success): void
    {
        $failureKey = "ai_circuit_failures_{$provider}";

        if ($success) {
            Cache::forget($failureKey);
            Cache::forget("ai_circuit_open_{$provider}");
            return;
        }

        $threshold = (int) \App\Models\Setting::get('ai_circuit_failure_threshold', 3);
        $failures = (int) Cache::get($failureKey, 0) + 1;
        Cache::put($failureKey, $failures, now()->addMinutes(10));

        if ($failures >= $threshold) {
            $cooldown = (int) \App\Models\Setting::get('ai_circuit_cooldown_minutes', 5);
            Cache::put("ai_circuit_open_{$provider}", true, now()->addMinutes($cooldown));
            Log::warning("AIService: circuit breaker opened for '{$provider}' after {$failures} consecutive failures; cooling down {$cooldown}m.");
            Cache::forget($failureKey);
        }
    }

    /**
     * Streaming tool-calling entry point used by ToolCallOrchestrator. Same
     * cascade order and same normalized return shape as the old blocking
     * chatWithTools(), but every provider attempt is made as a streaming
     * request so $onTextDelta can be invoked with genuine content chunks as
     * they arrive. A round that turns out to contain tool_calls is never
     * forwarded through $onTextDelta — only plain content deltas are (the
     * "stream only the final answer" scope: intermediate tool-resolution
     * rounds show the caller nothing but a static "thinking" state).
     */
    public function chatWithToolsStreaming(array $messages, array $tools, callable $onTextDelta): ?array
    {
        if ($this->geminiKey && !$this->providerIsOpen('gemini')) {
            $response = $this->streamGeminiLoop($messages, $tools, $onTextDelta);
            $this->recordProviderResult('gemini', (bool) $response);
            if ($response) return $response;
        }

        if ($this->groqKey && !$this->providerIsOpen('groq')) {
            $response = $this->streamOpenAiCompatibleLoop(
                $this->groqModels,
                'https://api.groq.com/openai/v1/chat/completions',
                ['Authorization' => 'Bearer ' . $this->groqKey],
                $messages,
                $tools,
                $onTextDelta
            );
            $this->recordProviderResult('groq', (bool) $response);
            if ($response) return $response;
        }

        if ($this->openRouterKey && !$this->providerIsOpen('openrouter')) {
            $response = $this->streamOpenAiCompatibleLoop(
                $this->openRouterModels,
                'https://openrouter.ai/api/v1/chat/completions',
                ['Authorization' => 'Bearer ' . $this->openRouterKey, 'HTTP-Referer' => config('app.url'), 'X-Title' => config('app.name')],
                $messages,
                $tools,
                $onTextDelta
            );
            $this->recordProviderResult('openrouter', (bool) $response);
            if ($response) return $response;
        }

        return null;
    }

    private function streamGeminiLoop(array $messages, array $tools, callable $onTextDelta): ?array
    {
        $models = $this->geminiModels;
        shuffle($models);
        $budget = $this->fastPathBudget();
        $models = array_slice($models, 0, max(1, $budget['modelLimit']));
        // Streaming needs a longer total-request ceiling than the blocking fast
        // path: responsiveness here comes from time-to-first-byte, not total
        // duration, since tokens render as they arrive. Kept just under the
        // client's 20s abort so the server never times out first.
        $timeout = self::STREAM_TIMEOUT;

        foreach ($models as $model) {
            try {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?alt=sse&key=" . $this->geminiKey;
                $contents = $this->buildGeminiContents($messages);
                $payload = ['contents' => $contents];
                if (!empty($tools)) {
                    $payload['tools'] = $this->toGeminiTools($tools);
                }

                $response = Http::timeout($timeout)->withOptions(['stream' => true])->post($url, $payload);

                if (!$response->successful()) {
                    if ($response->status() === 401) break;
                    continue;
                }

                $text = '';
                $toolCalls = [];
                $sawAny = false;

                $this->readSseStream($response->toPsrResponse()->getBody(), function (array $event) use (&$text, &$toolCalls, &$sawAny, $onTextDelta) {
                    foreach ($event['candidates'][0]['content']['parts'] ?? [] as $part) {
                        if (isset($part['text'])) {
                            $sawAny = true;
                            $text .= $part['text'];
                            $onTextDelta($part['text']);
                        }
                        if (isset($part['functionCall'])) {
                            $sawAny = true;
                            $toolCalls[] = [
                                'id' => 'call_' . Str::random(8),
                                'name' => $part['functionCall']['name'] ?? '',
                                'arguments' => $part['functionCall']['args'] ?? [],
                            ];
                        }
                    }
                });

                if ($sawAny) {
                    return ['choices' => [['message' => ['content' => $text ?: null, 'tool_calls' => $toolCalls]]]];
                }
            } catch (\Exception $e) {
                Log::error("Gemini Stream Loop Exception ({$model}): " . $e->getMessage());
            }
        }
        return null;
    }

    /** Shared by Groq and OpenRouter — both speak the OpenAI-compatible streaming delta format. */
    private function streamOpenAiCompatibleLoop(array $models, string $url, array $headers, array $messages, array $tools, callable $onTextDelta): ?array
    {
        $modelsList = $models;
        shuffle($modelsList);
        $budget = $this->fastPathBudget();
        $modelsList = array_slice($modelsList, 0, max(1, $budget['modelLimit']));
        $timeout = self::STREAM_TIMEOUT;

        foreach ($modelsList as $model) {
            try {
                $payload = ['model' => $model, 'messages' => $messages, 'stream' => true];
                if (!empty($tools)) {
                    $payload['tools'] = $this->toOpenAiTools($tools);
                }

                $response = Http::timeout($timeout)->withOptions(['stream' => true])->withHeaders($headers)->post($url, $payload);

                if (!$response->successful()) {
                    if ($response->status() === 401) break;
                    continue;
                }

                $text = '';
                $toolCallsByIndex = [];
                $sawAny = false;

                $this->readSseStream($response->toPsrResponse()->getBody(), function (array $event) use (&$text, &$toolCallsByIndex, &$sawAny, $onTextDelta) {
                    $delta = $event['choices'][0]['delta'] ?? [];

                    if (($delta['content'] ?? '') !== '') {
                        $sawAny = true;
                        $text .= $delta['content'];
                        $onTextDelta($delta['content']);
                    }

                    foreach ($delta['tool_calls'] ?? [] as $tc) {
                        $sawAny = true;
                        $index = $tc['index'] ?? 0;
                        $toolCallsByIndex[$index] ??= ['id' => null, 'name' => null, 'arguments' => ''];
                        if (isset($tc['id'])) $toolCallsByIndex[$index]['id'] = $tc['id'];
                        if (isset($tc['function']['name'])) $toolCallsByIndex[$index]['name'] = $tc['function']['name'];
                        if (isset($tc['function']['arguments'])) $toolCallsByIndex[$index]['arguments'] .= $tc['function']['arguments'];
                    }
                });

                if ($sawAny) {
                    $toolCalls = array_map(fn ($tc) => [
                        'id' => $tc['id'] ?? ('call_' . Str::random(8)),
                        'name' => $tc['name'] ?? '',
                        'arguments' => json_decode($tc['arguments'] ?: '{}', true) ?? [],
                    ], array_values($toolCallsByIndex));

                    return ['choices' => [['message' => ['content' => $text ?: null, 'tool_calls' => $toolCalls]]]];
                }
            } catch (\Exception $e) {
                Log::error("OpenAI-compatible Stream Loop Exception ({$model} @ {$url}): " . $e->getMessage());
            }
        }
        return null;
    }

    /**
     * Minimal SSE reader: buffers bytes from a PSR stream until a full
     * "data: ...\n" line is available, JSON-decodes it, and invokes
     * $onEvent. Skips the "[DONE]" terminator OpenAI-compatible APIs send.
     */
    private function readSseStream(\Psr\Http\Message\StreamInterface $body, callable $onEvent): void
    {
        $buffer = '';
        while (!$body->eof()) {
            $buffer .= $body->read(1024);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '' || !str_starts_with($line, 'data:')) {
                    continue;
                }

                $json = trim(substr($line, 5));
                if ($json === '' || $json === '[DONE]') {
                    continue;
                }

                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $onEvent($decoded);
                }
            }
        }
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
                'parameters' => $this->jsonSchemaSafeParameters($t['parameters']),
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
                'parameters' => $this->jsonSchemaSafeParameters($t['parameters']),
            ],
        ], $tools);
    }

    /**
     * PHP can't distinguish an empty array from an empty object — a
     * zero-parameter tool's `'properties' => []` (from AgentTool::parametersSchema())
     * json_encode()s as a JSON array, but Gemini and Groq both strictly
     * require a JSON object there and reject the request with a 400 (verified
     * live against both APIs). OpenRouter happens to tolerate it, which is
     * why this bug was silently invisible — every zero-parameter tool
     * (checkStockLevels, checkMySession, getActiveSessions,
     * shiftHandoffSummary) was quietly failing on the first two providers in
     * the cascade and only ever succeeding on the third.
     */
    private function jsonSchemaSafeParameters(array $parameters): array
    {
        if (isset($parameters['properties']) && $parameters['properties'] === []) {
            $parameters['properties'] = new \stdClass();
        }

        return $parameters;
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

    /**
     * Ultra-low-latency, best-effort phrasing for the POS upsell suggestion
     * toast. Tries exactly one provider with a hard ~2s cap and no cascade —
     * this fires on every single add-to-cart, so a slow/unavailable AI must
     * never hold up the (already-computed, always-correct) data-driven
     * suggestion. Returns null on any failure/timeout; caller must have a
     * simple template fallback.
     */
    public function phraseSuggestion(string $itemName, string $suggestedName): ?string
    {
        if (!$this->geminiKey || $this->providerIsOpen('gemini')) {
            return null;
        }

        try {
            $model = $this->geminiModels[0];
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->geminiKey;
            $prompt = "A customer just ordered '{$itemName}'. In under 12 words, write a friendly one-line suggestion to also get '{$suggestedName}'. No markdown, no quotes.";

            $response = Http::timeout(2)->post($url, [
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            ]);

            if (!$response->successful()) {
                $this->recordProviderResult('gemini', false);
                return null;
            }

            $text = trim($response->json('candidates.0.content.parts.0.text') ?? '');
            $this->recordProviderResult('gemini', $text !== '');
            return $text !== '' ? $text : null;
        } catch (\Exception $e) {
            $this->recordProviderResult('gemini', false);
            return null;
        }
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
