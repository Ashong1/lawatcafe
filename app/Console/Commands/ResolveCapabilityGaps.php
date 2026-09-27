<?php

namespace App\Console\Commands;

use App\Models\AiConversation;
use App\Models\AiFeedback;
use App\Models\AiLesson;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Services\Agent\CapabilityGap;
use App\Services\Agent\LessonLibrary;
use App\Services\Agent\PageCatalog;
use App\Services\Agent\ToolRegistry;
use App\Services\AiBudget;
use App\Services\AIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * The assistant teaching itself what it couldn't do.
 *
 * For each "I can't do that" CapabilityGap from staff/admin chat, the model
 * works out how it could have succeeded:
 *  - skill: steps over tools it already has — applied automatically; every
 *    step is checked against the live registry and each tool keeps its own
 *    permission tier;
 *  - page: a PageCatalog page the user's role can open — applied
 *    automatically;
 *  - tool_request: a drafted spec for a missing tool — never applied, goes to
 *    super_admin. The assistant never writes or runs code.
 * Applied items are revocable on the AI Learning page.
 */
class ResolveCapabilityGaps extends Command
{
    protected $signature = 'ai:resolve-gaps
        {--backfill : Also scan past staff/admin conversations for replies that were capability gaps}
        {--dry-run : Show the resolutions without saving}';

    protected $description = 'Teach the assistant how to handle requests it said it could not do: learn skills, page pointers, or draft tool requests.';

    private const MAX_GAPS_PER_RUN = 15;

    public function handle(AIService $ai, ToolRegistry $registry): int
    {
        if ($this->option('backfill')) {
            $this->info($this->backfill().' past gap(s) recovered from conversations.');
        }

        if (! app(AiBudget::class)->backgroundMaySpend()) {
            // Leave the day's last free AI requests for people — see AiBudget::BACKGROUND_RESERVE.
            $this->warn('Skipped: AI allowance is low or used up for today; gaps stay for the next run.');

            return self::SUCCESS;
        }

        $gaps = AiFeedback::undistilled()
            ->where('signal', AiFeedback::SIGNAL_CAPABILITY_GAP)
            ->orderBy('created_at')
            ->limit(self::MAX_GAPS_PER_RUN)
            ->get();

        if ($gaps->isEmpty()) {
            $this->info('No capability gaps to learn from.');

            return self::SUCCESS;
        }

        $learned = ['skill' => 0, 'page' => 0, 'tool_request' => 0];

        foreach ($gaps->groupBy('audience') as $audience => $group) {
            $tools = collect($registry->forAudience($audience))
                ->map(fn ($t) => ['name' => $t->name(), 'description' => $t->description(), 'inputs' => array_keys($t->parametersSchema()['properties'] ?? [])])
                ->values()->all();
            $pages = array_map(fn ($p) => ['route' => $p['route'], 'page' => $p['label'], 'purpose' => $p['purpose']], PageCatalog::forAudience($audience));

            $payload = $group->values()->map(fn ($g, $i) => [
                'gap' => $i,
                'user_asked' => Str::limit((string) $g->user_message, 500),
                'assistant_said' => Str::limit((string) $g->assistant_reply, 500),
            ])->all();

            $resolutions = $ai->resolveCapabilityGaps($payload, $tools, $pages);

            if ($resolutions === null) {
                // Outage: leave the gaps for the next run rather than lose them.
                $this->warn("AI stack unreachable — {$audience} gaps left for the next run.");

                continue;
            }

            $toolNames = array_column($tools, 'name');
            foreach ($resolutions as $resolution) {
                $gap = $group->values()->get((int) ($resolution['gap'] ?? -1));
                if (! $gap || ! is_array($resolution)) {
                    continue;
                }

                $kind = $this->store($resolution, $gap, $audience, $toolNames);
                if ($kind) {
                    $learned[$kind]++;
                }
            }

            if (! $this->option('dry-run')) {
                AiFeedback::whereIn('id', $group->pluck('id'))->update(['distilled_at' => now()]);
                app(LessonLibrary::class)->forget($audience);
            }
        }

        $this->info(sprintf('Learned %d skill(s), %d page pointer(s); drafted %d tool request(s).', $learned['skill'], $learned['page'], $learned['tool_request']));

        return self::SUCCESS;
    }

    /** Validate one resolution and save it. Returns the kind stored, or null if rejected. */
    private function store(array $r, AiFeedback $gap, string $audience, array $toolNames): ?string
    {
        $trigger = Str::limit(trim((string) ($r['trigger'] ?? '')), 200, '');
        $title = Str::limit(trim((string) ($r['title'] ?? $trigger)), 120, '');
        if ($trigger === '') {
            return null;
        }

        $evidence = [['asked' => Str::limit((string) $gap->user_message, 300), 'replied' => Str::limit((string) $gap->assistant_reply, 300)]];

        switch ($r['resolution'] ?? null) {
            case 'skill':
                $steps = collect($r['steps'] ?? [])
                    ->filter(fn ($s) => is_array($s) && in_array($s['tool'] ?? null, $toolNames, true))
                    ->map(fn ($s) => ['tool' => $s['tool'], 'how' => Str::limit(trim((string) ($s['how'] ?? '')), 300, '')])
                    ->values()->all();

                // Every step must name a real tool; one invented tool voids the skill.
                if ($steps === [] || count($steps) !== count($r['steps'] ?? [])) {
                    $this->line("  rejected skill '{$title}': names a tool that does not exist.");

                    return null;
                }

                $body = "When asked to {$trigger}: ".collect($steps)->map(fn ($s, $i) => ($i + 1).". call {$s['tool']}".($s['how'] ? " — {$s['how']}" : ''))->implode(' ');

                return $this->save($audience, AiLesson::KIND_SKILL, $title, $body, $trigger, $evidence + ['steps' => $steps], true) ? 'skill' : null;

            case 'page':
                $page = PageCatalog::find($audience, (string) ($r['page_route'] ?? ''));
                if (! $page) {
                    $this->line("  rejected page pointer '{$title}': not a page this role can open.");

                    return null;
                }

                $instruction = Str::limit(trim((string) ($r['page_instruction'] ?? '')), 300, '');
                $body = "When asked to {$trigger}: you can't do this with a tool, but the user can — tell them to open {$page['label']} ({$page['path']})".($instruction ? ": {$instruction}" : '.');

                return $this->save($audience, AiLesson::KIND_LESSON, $title, $body, $trigger, $evidence, true) ? 'page' : null;

            case 'tool_request':
                $spec = $r['tool_request'] ?? null;
                if (! is_array($spec) || empty($spec['name'])) {
                    return null;
                }

                $inputs = collect($spec['inputs'] ?? [])->filter(fn ($in) => is_array($in))
                    ->map(fn ($in) => ($in['name'] ?? '?').' ('.($in['type'] ?? 'string').'): '.($in['description'] ?? ''))->implode('; ');
                $body = Str::limit("New tool {$spec['name']} — ".($spec['description'] ?? '').($inputs ? " Inputs: {$inputs}." : '').(! empty($spec['uses']) ? " Would use: {$spec['uses']}." : '')." Requested by a {$audience} user.", 1000, '');

                if ($this->save(ToolRegistry::AUDIENCE_SUPER_ADMIN, AiLesson::KIND_TOOL_REQUEST, Str::limit('Tool request: '.$spec['name'], 120, ''), $body, $trigger, $evidence, false)) {
                    if (! $this->option('dry-run')) {
                        Notification::send(User::where('role', 'super_admin')->get(), new SystemAlert(
                            'Barista AI needs a new tool',
                            "It couldn't \"{$trigger}\" and drafted a tool for it: {$spec['name']}.",
                            'bot',
                            route('admin.ai.lessons.index')
                        ));
                    }

                    return 'tool_request';
                }

                return null;
        }

        return null;
    }

    private function save(string $audience, string $kind, string $title, string $body, string $trigger, array $evidence, bool $apply): bool
    {
        $fingerprint = hash('sha256', $audience.'|'.$kind.'|'.Str::lower($trigger));

        if (AiLesson::where('fingerprint', $fingerprint)->exists()) {
            return false;
        }

        $this->line("  [{$kind}] {$audience}: {$body}");

        if ($this->option('dry-run')) {
            return true;
        }

        AiLesson::create([
            'audience' => $audience,
            'kind' => $kind,
            'title' => $title !== '' ? $title : Str::limit($trigger, 120, ''),
            'body' => $body,
            'trigger' => $trigger,
            'evidence' => $evidence,
            'evidence_count' => 1,
            'status' => $apply ? AiLesson::STATUS_APPROVED : AiLesson::STATUS_PROPOSED,
            'reviewed_at' => $apply ? now() : null,
            'review_note' => $apply ? 'Self-learned: every tool/page verified against the live registry. Revoke here if wrong.' : null,
            'fingerprint' => $fingerprint,
        ]);

        return true;
    }

    /**
     * Recover gaps from before capture existed: assistant replies in saved
     * staff/admin conversations that read as "I can't", with no tool run
     * right after them.
     */
    private function backfill(): int
    {
        $found = 0;

        AiConversation::with('user')->whereIn('context', ['staff', 'admin'])->where('last_message_at', '>=', now()->subDays(30))
            ->each(function (AiConversation $c) use (&$found) {
                $messages = array_values($c->messages ?? []);
                $audience = $c->context === 'admin' && $c->user?->isSuperAdmin() ? ToolRegistry::AUDIENCE_SUPER_ADMIN : $c->context;

                foreach ($messages as $i => $m) {
                    $next = $messages[$i + 1] ?? null;
                    $prev = $messages[$i - 1] ?? null;
                    if (($m['kind'] ?? '') !== 'text' || ($m['role'] ?? '') !== 'assistant'
                        || in_array($next['kind'] ?? null, ['executed', 'pending'], true)
                        || ! CapabilityGap::looksLikeGap((string) ($m['content'] ?? ''))
                        || ($prev['role'] ?? '') !== 'user') {
                        continue;
                    }

                    $exists = AiFeedback::where('signal', AiFeedback::SIGNAL_CAPABILITY_GAP)
                        ->where('conversation_id', $c->id)->where('assistant_reply', $m['content'])->exists();
                    if ($exists || $this->option('dry-run')) {
                        continue;
                    }

                    AiFeedback::create([
                        'audience' => $audience,
                        'user_id' => $c->user_id,
                        'conversation_id' => $c->id,
                        'signal' => AiFeedback::SIGNAL_CAPABILITY_GAP,
                        'sentiment' => -1,
                        'user_message' => $prev['content'] ?? '',
                        'assistant_reply' => $m['content'],
                    ]);
                    $found++;
                }
            });

        return $found;
    }
}
