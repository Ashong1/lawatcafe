---
name: remove-ai-slop
description: Find and remove AI-generated "slop" from this Laravel app (Lawa't Kape POS + captive portal) — buzzword/filler UI copy, code names or snake_case shown to people, decorative emoji, changelog-diary comments, comments that restate code, and unused imports — without changing behaviour. Use this whenever the user asks to remove/clean up AI slop, de-slop, clean the copy, make the wording less robotic or "AI-sounding", tidy comments, trim over-long docblocks, or do a cleanup/polish pass on views or services — even if they don't say "slop".
---

# Remove AI slop

AI-written code and copy leave recognisable residue: marketing filler nobody
asked for, internal names leaking into the UI, and comments that narrate the
history of a change instead of explaining the code in front of you. The owner
of this app runs a cafe; every screen should read like a person wrote it for
them, and every comment should help the next developer, not re-tell a ticket.

The goal is **less noise, same behaviour, no lost knowledge**.

## Workflow

1. **Scan.** Run the bundled scanner from the project root:
   ```bash
   python3 .claude/skills/remove-ai-slop/scripts/scan_slop.py . --summary   # where the hits are
   python3 .claude/skills/remove-ai-slop/scripts/scan_slop.py . --only copy # then one area at a time
   ```
   It only reports candidates. Every hit needs a judgement call (below); a
   hit is not a bug until you've read it in context.
2. **Fix copy first** (views and user-facing PHP strings), then **comments**,
   then **code**. Copy is what the owner sees; it has the highest payoff.
3. **Verify** after each area — see *Verification*. Commit each area
   separately so a bad call is easy to revert.

## 1. Copy (Blade views, strings a person reads)

Read each hit in the rendered context and ask: *would the cafe owner say
this out loud?*

**Rewrite:**
- Buzzwords and filler — "Enterprise Intelligence", "Digital Concierge",
  "premium high-speed network", "seamless", "leverage", "powered by",
  "Live Monitoring" as a badge on a static panel. Say what the thing does:
  "Digital Concierge" → "Ask about the menu or Wi-Fi".
- Code names shown to people — `adjustFairUseCeiling`, `tool_request`,
  `ai_learning_auto_apply`. Use the plain name of the thing; for tool names
  reuse `App\Support\AgentActivityEntry::labelFor()`, for warning codes
  `AgentActivityEntry::signalLabel()`, so wording stays consistent.
- Decorative emoji in system messages ("☕ Staff AI stack offline."). An emoji
  that carries meaning a guest benefits from can stay; one stuck on the front
  of every error message is noise. Also watch internal jargon inside the
  same strings ("business intelligence stack").
- Vague or robotic status words — "Operation completed", "System Online",
  "Processing request". Say what happened: "Saved", "Blocked for all guests".

**Keep:**
- Real product vocabulary: *Premium* is a Wi-Fi tier name, *Z-Read* is the
  cashier term, *86 list* is kitchen slang staff use. Domain words aren't slop.
- Technical text on super_admin-only screens where the reader is the
  developer (job commands, model ids) — but still make surrounding sentences
  plain.

Match tone to the reader: guests get short and friendly; staff get direct;
errors say what happened, why, and what to do.

## 2. Comments

This codebase deliberately keeps long "why" comments — they record hard-won
lessons (a phone's sign-in window is destroyed when its probe succeeds; the
OpenRouter streamed body can only be read once). **That knowledge must
survive.** Slop is the packaging around it.

For each flagged block:

| Pattern | What to do |
|---|---|
| **Diary** — dates, versions, "used to", "previously", "Owner: '…'", "Live: …", who reported what | Rewrite in the present tense as the rule the code follows and why. Keep one short clause of history only if it's the reason ("a 16px circle can't hold a 12px digit"). Commit messages and git blame already hold the story. |
| **Restates the code** — `// increment the counter` over `$n++` | Delete. |
| **Over-long** (the scanner flags 12+ lines) | Cut to the invariant, the non-obvious reason, and the trap to avoid. Usually 2-6 lines survive. |
| **Genuine why / gotcha / invariant** | Keep, tighten wording if it rambles. |

Rewrite example — before:
```php
// Live: the admin chat answered "trouble connecting". On 2026-09-28 the
// OpenRouter account's free cap (50/day) was used up — 52/50 — and every model
// returned 429. Before v1.11.1.158 each chat still tried all the models.
```
after:
```php
// A free-models-per-day 429 means every other model will fail the same way
// (the cap is per account), so stop the cascade and pause until the reset.
```

Not every flagged word is history. "a previously-proposed action" or "a
domain that was previously toggled off" describe *state* the code handles —
leave those. The scanner already ignores "is used to determine"; judge the rest
by asking whether the sentence narrates a past change or describes the present.

Never change a comment's *meaning* to fit a shorter sentence, and never remove
a comment that explains why a test fixture or a workaround exists.

## 3. Code

- **Unused imports** — the scanner lists them; confirm with a search (a class
  may be used only in a docblock `@var`/`@return`, which still counts) then
  delete. `vendor/bin/pint` can also fix these.
- **Dead code** — only remove code you can prove is unreachable (no route,
  no caller, no Blade reference: `grep -rn` the name across `app/`,
  `resources/`, `routes/`, `tests/`). If a test is the only caller, ask.
- **Redundant defensive checks** — e.g. a null-check on something the type
  already guarantees. Remove only when the guarantee is local and obvious.

Don't refactor while de-slopping. The diff should read as deletions and
rewrites of words, not a redesign.

## Verification

Behaviour must not change, so after each area:

```bash
vendor/bin/pint --test <changed PHP files>          # style (Laravel preset)
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan test                   # full suite, not a subset
sudo -u www-data npm run build                      # if class names changed
```

- Tests that assert on copy (`assertSee('Old words')`) will fail when copy
  improves. Update the assertion to the new wording **and** keep the test's
  intent — never delete a test to make it pass.
- Only commit when the suite is green:
  `sudo -u www-data php artisan test 2>&1 | grep -q "Tests:.*failed" && exit 1`.
- Follow the project's commit rules (bump the BUILD number in composer.json
  every commit; run artisan as www-data; see AGENTS.md and project memory).

## Reporting back

Tell the owner, in plain words: what kinds of slop were found (with one or two
before/after examples), how much was changed, anything deliberately kept and
why, and that behaviour is unchanged with the test result. Skip the jargon —
they asked for less of it.
