<?php

namespace App\Services\Agent\Tools;

use App\Models\AiFeedback;
use App\Models\AiLesson;
use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\AIService;

/**
 * super_admin only. The state of the assistant's own stack — providers, circuit
 * breakers, and what it has been learning.
 *
 * Deliberately reports no API keys or key fragments, only whether a provider is
 * configured at all. "Which of my providers is down" is a legitimate question;
 * "what is my key" is not one the assistant should ever be able to answer.
 */
class GetAiStackStatusTool implements AgentTool
{
    public function __construct(protected AIService $ai) {}

    public function name(): string
    {
        return 'getAiStackStatus';
    }

    public function description(): string
    {
        return 'Check the AI stack itself: which model providers are configured and healthy, which circuit breakers are open, and what the assistant has learned recently (approved lessons, lessons awaiting review, guest/staff satisfaction). Use for "is the AI working", "why are replies slow or failing", or "what have you learned lately". Read-only.';
    }

    public function parametersSchema(): array
    {
        return ['type' => 'object', 'properties' => [], 'required' => []];
    }

    public function permissionTier(): string
    {
        return 'auto';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $providers = [];
        $down = [];

        foreach ($this->ai->getProviderStatuses() as $key => $provider) {
            $failing = count(array_filter($provider['models'], fn ($m) => ($m['status'] ?? null) === 'failed'));
            $total = count($provider['models']);

            $providers[] = [
                'provider' => $key,
                'configured' => $provider['configured'],
                'circuit_open' => $provider['circuit']['open'],
                'models_usable' => $total - $failing,
                'models_total' => $total,
            ];

            if ($provider['configured'] && $provider['circuit']['open']) {
                $down[] = $key;
            }
        }

        $summary = empty($down)
            ? 'The AI stack is healthy — no provider circuits are open.'
            : 'Circuit breaker open on: '.implode(', ', $down).'. Replies will be falling back to the remaining providers.';

        return ToolResult::ok($summary, [
            'providers' => $providers,
            'learning' => [
                'lessons_in_force' => AiLesson::approved()->count(),
                'lessons_awaiting_review' => AiLesson::proposed()->count(),
                'satisfaction_last_7_days_percent' => AiFeedback::satisfactionRate(7),
                'ratings_last_7_days' => AiFeedback::where('signal', AiFeedback::SIGNAL_RATING)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
            ],
        ]);
    }
}
