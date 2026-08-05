<?php

namespace App\Services\Agent\Tools;

use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;

/**
 * super_admin only. Who has access, and at what level.
 *
 * Names and roles only — never email addresses, password state or last-login
 * detail. "How many admins do I have" is an access-review question; a roster
 * with contact details in a chat transcript is a data-protection problem, and
 * the Accounts page already shows the full record to someone who navigates to
 * it deliberately.
 */
class ListUserAccountsTool implements AgentTool
{
    public function name(): string
    {
        return 'listUserAccounts';
    }

    public function description(): string
    {
        return 'List the system\'s user accounts by role (super admin, admin, staff), with names and when each was created. Use for access reviews: "who has admin access", "how many staff accounts are there", "has anyone been added recently". Read-only.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'role' => ['type' => 'string', 'description' => 'Optionally narrow to one role: super_admin, admin or staff.'],
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
        $query = User::query()->orderBy('role')->orderBy('name');

        $role = $arguments['role'] ?? null;
        if (in_array($role, ['super_admin', 'admin', 'staff'], true)) {
            $query->where('role', $role);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            return ToolResult::ok('No matching accounts found.', ['accounts' => []]);
        }

        $byRole = $users->groupBy('role')->map->count();

        $summary = $users->count().' account(s): '
            .$byRole->map(fn ($count, $r) => "{$count} ".str_replace('_', ' ', $r))->implode(', ').'.';

        return ToolResult::ok($summary, [
            'accounts' => $users->map(fn (User $u) => [
                'name' => $u->name,
                'role' => $u->roleLabel(),
                'created' => $u->created_at?->toDateString(),
            ])->all(),
        ]);
    }
}
