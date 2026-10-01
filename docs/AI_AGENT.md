# Barista AI (the agent)

This is the capstone's core claim (see [ARCHITECTURE.md](ARCHITECTURE.md)):
the AI isn't a chatbot bolted onto the POS and the network. It is an agent
that can **act** on both (check stock, void a sale, block a device, change a
guest's speed, draft a purchase order), under a real permission system and
audit trail.

- [The pieces](#the-pieces)
- [Audiences and tools](#audiences-and-tools)
- [Permission tiers](#permission-tiers)
- [Confirming and rejecting](#confirming-and-rejecting)
- [The model: OpenRouter, fallback and budget](#the-model-openrouter-fallback-and-budget)
- [The scheduled analysis](#the-scheduled-analysis-agentanalyze)
- [Learning](#learning)
- [Other AI features](#other-ai-features)
- [Guest chat](#guest-chat)
- [When the internet is down](#when-the-internet-is-down)

---

## The pieces

| Class | Job |
|---|---|
| `App\Services\AIService` | Transport only: sends a conversation to OpenRouter, gets text or tool calls back, streams replies. Owns model fallback, the circuit breaker, per-model health and the cached context used in prompts. Knows nothing about permissions. |
| `App\Services\Agent\ToolRegistry` | The fixed list of tool classes each audience may use. Not built from settings, so a misconfigured setting can never hand guests a tool. |
| `App\Services\Agent\PermissionResolver` | Works out the effective tier (`auto` / `confirm` / `admin_only`) for one tool and one person. |
| `App\Services\Agent\ToolCallOrchestrator` | The loop: the model asks for a tool → run it or queue it for approval → feed the result back → the model answers. Up to 5 rounds, within one overall time budget. |
| `App\Services\Agent\AuditLogger` | Writes an `ai_action_audits` row for every tool call. |
| `App\Services\Agent\ChatStreamResponder` | Streams replies to the chat widget (server-sent events), with plain messages when the daily allowance is used up or the internet is down. |
| `App\Services\Agent\Tools\*` | One class per tool (32), each implementing `AgentTool`: `name()`, `description()`, `parametersSchema()`, `permissionTier()`, `execute()`. |
| `App\Services\AiBudget` | How much of OpenRouter's free daily allowance is left, and whether background jobs may spend some. |
| `resources/js/agent-chat.js` | The chat widget: streaming, history, photo attach, approvals, draggable button. |

## Audiences and tools

Each audience includes every tool of the one above it.

**Guest (2)**: only ever about the guest's own device.

| Tool | Tier | Does |
|---|---|---|
| `lookupVoucher` | auto | Details of a code the guest gives |
| `checkMySession` | auto | The guest's own session: time left, data, plan |

**Staff (+13 = 15)**: network first, then the shop.

| Tool | Tier | Does |
|---|---|---|
| `checkNetworkHealth` | auto | Latest health checks |
| `lookupDevice` | auto | One device by IP, MAC, name or code |
| `getTopBandwidthUsers` | auto | Who is using the most data |
| `getActiveSessions` | auto | Who is online |
| `getTrafficStats` | auto | Live throughput |
| `checkStockLevels` | auto | Stock of one item or everything low |
| `restockIngredient` | confirm | Add stock |
| `voidSale` | confirm | Void a sale |
| `draftSupplierPo` | confirm | Draft a purchase order |
| `sendSupplierPo` | confirm | Email a drafted order to the supplier |
| `listSupplierPoDrafts` | auto | Drafted orders |
| `shiftHandoffSummary` | auto | The caller's shift so far |
| `getSalesSummary` | auto | Sales for a period |

**Admin (+11 = 26)**

| Tool | Tier | Does |
|---|---|---|
| `getDnsStats` | auto | Pi-hole lookups and blocks |
| `blockDevice` / `unblockDevice` | admin_only | Ban a device (and disconnect it) / lift a ban. Protected addresses are refused |
| `blockSites` / `unblockSites` | admin_only | Block or unblock websites through Pi-hole |
| `listBlockedSites` | auto | What is blocked |
| `setSessionBandwidthTier` | admin_only | Move a guest between Free and Premium |
| `adjustFairUseCeiling` | auto | Move the per-device ceiling, always clamped to the owner's min/max |
| `getAnomalySignals` | auto | The cross-domain signals described below |
| `generateVoucherBatch` | admin_only | Create Wi-Fi codes |
| `suggestCategoryContent` | confirm | Description and icon for a menu category |

**Super admin (+6 = 32)**: read-only views of the system itself.

| Tool | Tier | Does |
|---|---|---|
| `getPortalPosture` | auto | Who bypasses the portal, banned devices, unused code stock, codes stuck without internet |
| `getSystemHealth` | auto | App server CPU load, memory, disk, temperature, database and cache |
| `getScheduledJobHealth` | auto | Whether each scheduled job is running (heartbeats) |
| `getAiStackStatus` | auto | Models, circuit breaker, daily allowance |
| `getRecentSystemErrors` | auto | Recent application errors |
| `listUserAccounts` | auto | Who holds which account |

The super admin's AI can't write code. When asked for something no tool
does, it says so and points to what it can do.

`ToolCallOrchestrator` checks the audience's registry again when it runs a
tool, regardless of which tools the model was shown. So even a prompt
injection that gets the model to *ask* for an out-of-audience tool gets
nothing.

## Permission tiers

| Tier | Meaning |
|---|---|
| `auto` | Runs immediately |
| `confirm` | Stored as *proposed*; a person approves or rejects it |
| `admin_only` | Proposed, and only an admin or super admin can approve it |

`PermissionResolver::tierFor()` combines:

1. **The tool's own tier.** If it is `admin_only`, that is final; no setting
   can loosen it.
2. **An optional override** from System Administration → Agent Permissions (`agent_tool_permissions`),
   for the other tools.
3. **The person's floor**: a staff member never gets `auto` for anything
   above `auto`. Scheduled runs (no person) and admins have no floor.

A tool that declares a tier the resolver doesn't know is treated as
`admin_only`, the safe failure. `AgentToolTierTest` checks every tool's
declared tier, after exactly that happened to `adjustFairUseCeiling`, which
left the adaptive ceiling unable to act on its own (fixed in 1.18).

## Confirming and rejecting

A tool call above `auto` is written to `ai_action_audits` as `proposed`, and
the model stops for that turn rather than improvising around an action that
hasn't happened. It shows up in the chat with Approve / Reject buttons, and
on **AI Actions** (`/admin/ai/actions`).

Approving checks ownership, not just role: an admin can act on any proposal,
while a staff member only on their own (or on one from a scheduled run with
no person behind it). Without that check, any staff account could approve
someone else's action by guessing an ID.

Audit rows record the tool, its input (encrypted at rest), the result, who
asked and who approved.

## The model: OpenRouter, fallback and budget

- **One provider, OpenRouter.** It replaced the earlier Gemini/Groq/OpenRouter
  cascade in 1.10, at the adviser's request. The key comes from Settings →
  AI Providers or `OPENROUTER_API_KEY`.
- **Model fallback**: a configurable list of models is tried in turn.
  Models that failed in the last few minutes go to the back of the line
  (`healthyModelsFirst()`), and the guest portal only ever uses free models.
- **Circuit breaker**: after 3 failures in a row the provider rests for 5
  minutes (`ai_circuit_failure_threshold`, `ai_circuit_cooldown_minutes`).
- **Time limits**: interactive chat gives each model 7 seconds and tries 2
  (`fast_path_*`). A streamed reply shares one 18-second deadline across all
  models, under the browser's 20-second limit. A whole agent turn, including
  tool calls, is capped at 60 seconds (`agent_conversation_budget_seconds`).
- **Daily allowance**: OpenRouter's free models allow 50 requests a day per
  account (1,000 with $5 of credit). When it is used up the app remembers
  until the reset and tells people plainly when it comes back. Scheduled AI
  jobs stop at a reserve of 15 so people chatting always have some left
  (`AiBudget`).
- **Photos**: a message with a photo only goes to models that accept images.

## The scheduled analysis (`agent:analyze`)

Every 15 minutes `CrossDomainCorrelationService` looks at sales and the
network **together**. Plain thresholds, not AI, decide whether something is
wrong, so the findings are testable and cheap:

| Signal | Means |
|---|---|
| Wi-Fi redemptions up while sales are flat | People may be getting Wi-Fi without buying |
| One device using many codes in 24 hours | Possible code sharing or abuse |
| A blocked device still has a session | The block isn't enforced on the firewall |
| An ingredient is low while its products keep selling | It will run out |

If anything fires, Barista AI explains it in plain words, the run and its
findings are saved (`ai_analysis_runs`, `ai_findings`; each finding says
whether staff see it or only admins), and the same orchestrator runs as the
admin audience with no person attached. A proposed `admin_only` action from
a scheduled run is therefore still only proposed. History is at
`/ai/analysis-history`.

## Learning

Barista AI improves from use, without retraining a model:

1. **Signals**: thumbs up/down and corrections on replies (`ai_feedback`),
   failures, and settled admin and super admin conversations
   (`ai_conversations.mined_at` marks them read). Guest chats are not mined.
2. **`ai:learn`** (hourly) distils them into candidate **lessons**
   (`ai_lessons`): a title, the rule, when it applies, and the evidence.
3. **The owner approves** each lesson on `/admin/ai/lessons`. Only approved
   lessons are added to prompts, for the audience they belong to. The super
   admin's infrastructure lessons never reach the shop admin's prompt.
   Approval is the guard against prompt injection: nothing a user types
   becomes an instruction without a person agreeing.
4. Lessons can be withdrawn; `times_applied` shows how often each is used.

**Capability gaps** (`ai:resolve-gaps`, hourly): when the AI says it can't
do something, that request is reviewed. The result is a learned skill (how
to do it with existing tools), a pointer to the right page, or a request
for a new tool sent to the super admin. The AI never writes code.

## Other AI features

| Feature | Where | Notes |
|---|---|---|
| Daily brief and insights | Dashboard, `/admin/ai/insights` | Cached; the forecast is pre-computed every 3 hours (`ai:warm-forecast`) |
| 7-day revenue forecast | Analytics | `BaristaForecastService` |
| Shift shortage audit | Shift close | Summary of a short drawer, emailed (queued) |
| "Say to customer" lines | Register | English and Tagalog; fixed sentence first, AI version prepared in the background (`PhrasePairingLine` job) |
| Category description and icon | Inventory → Categories | One `AIService` method shared by the page and the `suggestCategoryContent` tool |

## Guest chat

- Shown on the portal pages, using the **guest** audience: `lookupVoucher`
  and `checkMySession`, nothing else.
- The guest's IP and MAC come from the firewall for that request, never
  from the model or the browser. `CheckMySessionTool` is tested to ignore a
  model-supplied address, which would otherwise let a guest look at someone
  else's session.
- Common questions (where's my code, opening hours, Wi-Fi prices, how to
  reconnect) are answered from settings without an AI call
  (`PortalQuickReplies`).
- Not stored: guest chats are never written to `ai_conversations`.
- Every chat endpoint rejects history entries that try to pose as
  `system` or tool messages (prompt-injection hardening).

## When the internet is down

`InternetStatus::isDown()` (read from the every-minute health check, never
probing itself) makes every AI path stop at once: chat, scheduled jobs, the
register's phrasing and the model catalogue. The chat answers "the internet
is down, everything else still works" instead of timing out.
