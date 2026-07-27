<?php

namespace App\Services\Agent\Contracts;

use App\Models\User;
use App\Services\Agent\ToolResult;

/**
 * A single AI-agent-callable action. Concrete tools are thin wrappers over
 * the App\Services\* business-logic services — a tool should never contain
 * business logic itself, only argument mapping.
 */
interface AgentTool
{
    /** Stable machine name the model uses to call this tool, e.g. "restockIngredient". */
    public function name(): string;

    /** Natural-language description fed to the model so it knows when to call this. */
    public function description(): string;

    /**
     * JSON-Schema-ish parameter definition, e.g.:
     * ['type' => 'object', 'properties' => ['x' => ['type' => 'string']], 'required' => ['x']]
     */
    public function parametersSchema(): array;

    /**
     * Default permission tier for this tool: 'auto' | 'confirm' | 'admin_only'.
     * This is the fallback used when the Setting-based override doesn't mention
     * this tool by name (see PermissionResolver).
     */
    public function permissionTier(): string;

    /**
     * Execute the tool. $context carries request-scoped data a tool may need
     * that isn't part of the model-supplied arguments (e.g. the guest's own
     * client IP/MAC for session-scoped guest tools) — most tools ignore it.
     */
    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult;
}
