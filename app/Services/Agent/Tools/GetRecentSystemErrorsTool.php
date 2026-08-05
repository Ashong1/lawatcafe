<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * super_admin only. The most recent application errors, so "something is broken"
 * can be answered with what actually broke.
 *
 * This is the most sensitive tool in the registry — a log carries file paths,
 * SQL, and occasionally values from a failed request. Three deliberate limits:
 *
 *  1. Only the error headline lines are read, never the stack traces beneath
 *     them. A trace is where the incidental data lives, and it is unreadable in
 *     a chat bubble anyway.
 *  2. The tail is bounded so a huge log cannot be pulled into a prompt.
 *  3. Anything resembling a credential or token in a message is redacted before
 *     it leaves this method.
 *
 * The point is triage — "what is failing, and since when" — not a log viewer.
 */
class GetRecentSystemErrorsTool implements AgentTool
{
    /** Bytes read from the end of the log. Enough for recent history, bounded. */
    private const TAIL_BYTES = 120000;

    private const MAX_LINES = 15;

    public function name(): string
    {
        return 'getRecentSystemErrors';
    }

    public function description(): string
    {
        return 'Look at the most recent application errors recorded in the system log, grouped by how often each has occurred. Use when the owner reports something is broken, asks "are there any errors", or wants to know what failed recently. Read-only.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'hours' => ['type' => 'integer', 'description' => 'How far back to look, in hours. Defaults to 24.'],
            ],
            'required' => [],
        ];
    }

    public function permissionTier(): string
    {
        return 'auto';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $hours = max(1, min(168, (int) ($arguments['hours'] ?? 24)));
        $since = now()->subHours($hours);

        // Read the path the app is actually configured to log to rather than
        // hardcoding it — that keeps this correct if the channel is ever
        // changed, and lets tests point at a scratch file instead of truncating
        // and restoring the real multi-megabyte log on a live box.
        $path = config('logging.channels.single.path') ?: storage_path('logs/laravel.log');

        if (! is_readable($path)) {
            return ToolResult::ok('No application log is available to read.', ['errors' => []]);
        }

        $size = filesize($path);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ToolResult::ok('The application log could not be opened.', ['errors' => []]);
        }

        if ($size > self::TAIL_BYTES) {
            fseek($handle, -self::TAIL_BYTES, SEEK_END);
            // The first line after seeking mid-file is almost certainly a
            // fragment; drop it rather than report half a message.
            fgets($handle);
        }

        // Group identical messages instead of listing every occurrence: "this
        // failed 340 times since Tuesday" is the useful shape, and a raw list
        // would be 340 near-identical lines in a chat bubble.
        $grouped = [];

        while (($line = fgets($handle)) !== false) {
            if (! preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*?\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/', $line, $m)) {
                continue; // stack-trace lines and lower severities
            }

            try {
                $at = Carbon::parse($m[1]);
            } catch (\Throwable $e) {
                continue;
            }

            if ($at->lt($since)) {
                continue;
            }

            $message = $this->redact(trim($m[3]));
            // Collapse on the first sentence: the same fault re-thrown carries
            // varying ids and paths after it, which would defeat grouping.
            $key = Str::limit($message, 120, '');

            if (! isset($grouped[$key])) {
                $grouped[$key] = ['message' => $key, 'count' => 0, 'level' => $m[2], 'first_seen' => $at, 'last_seen' => $at];
            }

            $grouped[$key]['count']++;
            $grouped[$key]['last_seen'] = $at;
        }

        fclose($handle);

        if (empty($grouped)) {
            return ToolResult::ok("No errors recorded in the last {$hours} hours.", ['errors' => []]);
        }

        usort($grouped, fn ($a, $b) => $b['count'] <=> $a['count']);
        $top = array_slice($grouped, 0, self::MAX_LINES);

        $errors = array_map(fn ($e) => [
            'message' => $e['message'],
            'level' => $e['level'],
            'occurrences' => $e['count'],
            'first_seen' => $e['first_seen']->diffForHumans(),
            'last_seen' => $e['last_seen']->diffForHumans(),
        ], $top);

        $total = array_sum(array_column($grouped, 'count'));

        return ToolResult::ok(
            "{$total} error(s) in the last {$hours} hours, across ".count($grouped).' distinct message(s).',
            ['errors' => $errors]
        );
    }

    /**
     * Strip anything that looks like a secret before it leaves this method.
     *
     * Best-effort by nature — a log is unstructured text — but it removes the
     * predictable shapes: key=value pairs for sensitive names, and long
     * high-entropy tokens.
     */
    private function redact(string $message): string
    {
        $message = preg_replace(
            '/\b(password|passwd|secret|token|api[_-]?key|authorization|bearer)\b\s*[=:]\s*\S+/i',
            '$1=[redacted]',
            $message
        );

        return preg_replace('/\b[A-Za-z0-9_\-]{32,}\b/', '[redacted]', $message);
    }
}
