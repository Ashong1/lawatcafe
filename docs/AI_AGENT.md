# AI Agent

This is the capstone's core thesis (see `docs/ARCHITECTURE.md`): the AI isn't
a chatbot bolted onto the POS/network system, it's an agent that can actually
*act* on both domains — check stock, void a sale, block a device, draft a
purchase order — subject to a real permission system and audit trail, not
just answer questions about them.

## The pieces

| Class | Responsibility |
|---|---|
| `App\Services\AIService` | Pure transport: calls a provider (Gemini/Groq/OpenRouter), gets text or tool-calls back. Owns the multi-provider cascade, circuit breaker, and cached prompt-context queries. Knows nothing about permissions or audit logging. |
| `App\Services\Agent\ToolRegistry` | Per-audience (`guest`/`staff`/`admin`) hardcoded allowlist of which tool *classes* exist for that audience. |
| `App\Services\Agent\PermissionResolver` | Resolves the effective permission tier (`auto`/`confirm`/`admin_only`) for a given tool + actor, combining the tool's own default, an optional admin override, and a role floor. |
| `App\Services\Agent\ToolCallOrchestrator` | Owns the multi-turn "model asks for a tool → execute or queue for confirmation → feed the result back → model replies" loop. |
| `App\Services\Agent\AuditLogger` | Writes/updates `ai_action_audits` rows for every tool call attempt. |
| `App\Services\Agent\Tools\*` | One class per tool (17 as of this audit), each implementing `AgentTool` (`name()`, `description()`, `parametersSchema()`, `permissionTier()`, `execute()`). |
| `App\Console\Commands\RunAgentAnalysis` (`agent:analyze`) | Scheduled (every 15 minutes, `routes/console.php`) cross-domain correlation pass — the *proactive* half of the agent, as opposed to reactive chat. |

## Tool registry & audiences

`ToolRegistry` is deliberately **not** built from `Setting` (admin-editable
config) — guest safety must never depend on an admin not misconfiguring
something. The guest list is two read-only, self-scoped tools
(`lookupVoucher`, `checkMySession`); staff adds shop-wide read tools plus a
handful of `confirm`-tier write tools (void a sale, restock, draft/send a
PO); admin adds everything, including the hardcoded-`admin_only` tools
(`generateVoucherBatch`, `blockDevice`, `unblockDevice`,
`setSessionBandwidthTier`).

`ToolCallOrchestrator::run()` re-checks the audience-scoped registry at
execution time too (`ToolRegistry::forAudience($audience)[$toolName] ?? null`)
— independent of whatever tools the model was actually prompted with. This
is the second of two guest-isolation layers: even if a prompt-injection
attack got the model to *request* an out-of-audience tool name, the
orchestrator won't resolve it to anything executable.

## Permission tiers

Three tiers, in order of strictness: `auto` (executes immediately),
`confirm` (queued to `ai_action_audits` as `'proposed'`, needs a human to
approve), `admin_only` (either the tool's hardcoded default, or `confirm`
raised to `admin_only` for a staff actor).

`PermissionResolver::tierFor()` combines three things:
1. An optional admin-editable override (`Setting('agent_tool_permissions')`,
   JSON-encoded, managed via the agent-permissions settings page).
2. The actor's **role floor** — a staff actor can never get `auto`
   execution for a `confirm`-tier tool no matter what the setting says;
   an admin actor has no floor imposed.
3. A tool whose own hardcoded default is `admin_only` is **never**
   configurable via settings at all, for anyone — these are the
   direct-financial/network-access tools (`blockDevice`,
   `generateVoucherBatch`, etc.), picked specifically so an admin can't
   fat-finger the settings UI into making them staff-confirmable.

## The confirm/reject flow

When a tool call resolves to anything above `auto`, `ToolCallOrchestrator`
writes a `'proposed'` `ai_action_audits` row and stops looping the model for
that turn — it surfaces the pending action to the caller rather than letting
the model improvise around a not-yet-executed result. `AiActionController`
exposes `confirmPending()`/`rejectPending()` to actually approve or reject
one later.

Both confirm and reject re-check ownership, not just role: an admin can act
on any org-wide pending action, but a staff member may only confirm/reject
**their own** proposals (an audit's `actor_user_id` must match, or be null —
a scheduled/system-initiated proposal with no specific actor is fair game
for any staff+ account). This matters because route-model binding alone
doesn't scope this — without the check, any authenticated staff account
could act on another user's pending action just by guessing/incrementing
an audit ID.

## Provider cascade, circuit breaker, and health-aware retry

`AIService` tries providers in order — Gemini → Groq → OpenRouter — and
within each provider, a curated list of models. Two mechanisms sit on top of
that plain cascade:

- **Circuit breaker per provider** (`ai_circuit_open_{provider}` /
  `ai_circuit_failures_{provider}` cache keys). After `ai_circuit_failure_threshold`
  (default 3) consecutive failures, a provider is skipped entirely for
  `ai_circuit_cooldown_minutes` (default 5) rather than retried on every
  request.
- **Health-aware model ordering** (`AIService::healthyModelsFirst()`).
  `recordModelResult()` tracks per-model success/failure (feeds the
  super_admin AI-provider status page); `healthyModelsFirst()` stable-partitions
  each provider's model list so a model that failed in the last 5 minutes is
  tried last within that provider, instead of being retried as eagerly as a
  healthy one and wasting a `fast_path_model_limit` slot.

A **conversation-level deadline** (`agent_conversation_budget_seconds`
Setting, default 60s) bounds `ToolCallOrchestrator::run()`'s whole loop —
each of up to 5 round trips gets its own ~18s provider-cascade budget, which
has no ceiling on the whole conversation otherwise (~90s worst case).
Interactive chat is shielded by the client's 20s `fetch()` abort regardless,
but `agent:analyze`'s cron job calls `run()` directly with no client-side
timeout at all — this is what actually bounds it there.

## The scheduled proactive pass (`agent:analyze`)

`CrossDomainCorrelationService::run()` is pure, deterministic, non-LLM
analysis over both the POS and network domains — thresholds decide *whether*
something is anomalous (auditable, testable, cheap); only the narration/action
choice on top is AI. Four signal types: voucher-redemption-vs-revenue
divergence, repeat-MAC voucher abuse, a banned device that's still holding an
active session (block not enforced at the firewall), and a low-stock
ingredient whose linked products are still selling.

If any signals fire, `AIService::interpretSignals()` narrates them, the run
+ findings are persisted (`ai_analysis_runs`/`ai_findings` — a finding's
`audience` decides whether staff see it too, or it's admin-only), and the
same `messages` + `ToolCallOrchestrator::run()` pipeline interactive chat
uses gets called with `AUDIENCE_ADMIN` and a `null` actor — so a proposed
`admin_only` action from a scheduled run still lands as `'proposed'` in the
audit trail, never auto-executed just because there was no human in the loop
to ask.

## Guest chat vs. staff/admin chat

Guest (captive portal) chat is intentionally **not** persisted to
`ai_conversations` — it's ephemeral, scoped to the guest's own session, by
design. Staff/admin chat is persisted with full history, reachable via
`ai/conversations`. Guest tool context (own IP/MAC) is always request-scoped
server data passed through `$context`, never model-supplied — see
`CheckMySessionTool`'s "context IP/MAC only" guarantee, which is
regression-tested precisely because a model-supplied IP/MAC there would be a
guest-isolation bypass.
