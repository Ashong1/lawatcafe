# Testing

This project's actual testing conventions — useful for a future contributor,
or for walking a capstone panel through the process behind the product, not
just the result.

## Stack

**PHPUnit, not Pest.** `tests/Feature/` for everything (no meaningful
`tests/Unit/` split — almost nothing here is a pure unit worth isolating
from the framework). `RefreshDatabase` + model factories/`::create()` calls
are the norm; SQLite runs the suite, MySQL runs production (a couple of
raw-SQL spots — e.g. `DAYNAME()` in `Admin\AnalyticsController` — branch on
`getDriverName()` to work under both).

Run the whole suite:

```bash
sudo -u www-data php artisan test
```

## Always run Artisan as `www-data`, not root

Running `php artisan` (test, migrate, cache commands — anything) as `root`
on this live deployment has caused a real production outage before: mixed
cache-file ownership between root-written and www-data-written files broke
the app with a 500. Every Artisan invocation in this project's history is
`sudo -u www-data php artisan ...` — there is no exception to this, including
one-off `tinker` debugging sessions.

## Static analysis

```bash
sudo -u www-data vendor/bin/phpstan analyse   # level 5, phpstan-baseline.neon
sudo -u www-data vendor/bin/pint --test       # Laravel preset, style only
```

The baseline exists because this codebase has no `laravel-ide-helper` model
docblocks — PHPStan/Larastan can't statically resolve Eloquent magic
properties, relations, or custom query scopes called through `whereHas()`/
`with()` closures without them, which is almost the entirety of what's
baselined. Regenerate the baseline (`--generate-baseline`) after intentional
changes rather than hand-editing it; spot-check a sample of new entries
before trusting a regenerated baseline is still "all false positives."

## Verifying cookie-dependent behavior: curl over PHPUnit's cookie helpers

`withCookie()` in a PHPUnit test **auto-encrypts** the value through
Laravel's `EncryptCookies` middleware — which silently hides bugs in code
that reads a plain, unencrypted, JS-set cookie (e.g. an Alpine
`x-data`/`$watch` value persisted via `document.cookie`). A real regression
here (sidebar-menu state not persisting) passed every PHPUnit test using
`withCookie()` while being completely broken in an actual browser, because
the test was encrypting the cookie for it. Use `withUnencryptedCookie()`, or
drop to raw `curl` with a real cookie jar, whenever a test needs to reproduce
what a plain-JS-set cookie actually looks like on the wire.

## Curl-based functional smoke passes

This project has no browser/E2E tooling (no Playwright/Cypress/Dusk, and no
MCP browser tool has been available during any audit session so far). For
flows that span multiple real requests/redirects/sessions — the kind of bug
that only shows up when the *actual* controller-to-controller lifecycle
runs, not a fabricated fixture — a raw curl pass against the live running
app (with a real cookie jar) is this project's substitute for E2E testing.

This matters more than it sounds: the cash-reconciliation bug documented in
`docs/AUDIT_FINDINGS.md` was invisible to 500+ passing PHPUnit tests because
every one of them created a `Sale` directly with `status: 'completed'` via a
factory/fixture — bypassing the real `checkout()`-always-creates-`'pending'`
→ KDS-completes-it lifecycle entirely. A curl pass that actually calls
`/pos/checkout` then `/shift/end` in sequence, the way a real cashier would,
caught it immediately.

**Safety rule for this specific project**: this app is a live, in-use
deployment with real shift/sale/voucher data, not a disposable staging
environment. A curl smoke pass against POS/shift endpoints must use a
disposable throwaway test account (created via `tinker`, deleted
immediately after) and must not touch real open shifts or existing sales.
Separately, **never exercise the captive-portal voucher-redeem flow via
live curl** — `CaptivePortalController::authenticate()` calls
`OpnSenseService::authorizeDevice()`, a real API call against the live
production firewall (`.env`'s `OPNSENSE_*` credentials point at a real box),
so redeeming a test voucher would mutate real network infrastructure, not
just an app DB row. That path's correctness is covered by PHPUnit tests that
mock `OpnSenseService` instead (`NetworkToolsTest`, `VoucherControllerTest`).

## Mocking external calls

- **AI provider calls**: `$this->mock(AIService::class, fn ($mock) => $mock->shouldReceive(...))`
  — never let a test hit a real Gemini/Groq/OpenRouter endpoint. A prior
  session's regression: an existing, previously-passing test started making
  real AI + Mail calls after an unrelated change added a new code path the
  test's existing mocks didn't cover — watch for this whenever a shared
  service gains a new call site.
- **Mail**: `Mail::fake()` + `Mail::assertSent()`/`assertNothingSent()`.
- **OPNsense**: `$this->mock(OpnSenseService::class, ...)` — every test that
  touches network/voucher tools mocks this; nothing in the suite makes a
  real OPNsense API call.

## Known test-writing gotchas

- **`admin@gmail.com` always exists.** A data migration
  (`2026_07_27_150148_promote_asherlimbo_to_super_admin`) unconditionally
  seeds a real admin account on every migration run, including under
  `RefreshDatabase`. A test asserting "zero admins" behavior isn't reachable
  without explicitly deleting admin/super_admin users first — not a bug,
  just something to remember when writing a from-scratch-state test.
- **`Playwright`'s `.click()` auto-scrolls** (irrelevant now — no browser
  tool is in use — but documented here in case one is added later) caused a
  false-positive scroll-position finding mid-investigation in an earlier
  session.
- **`replace_all` can silently miss a block with different indentation**
  when doing a mechanical find-and-replace across a Blade file — always
  assert on the rendered *values* a fix produces, not just that a function
  name appears in the output.

## Commit discipline for multi-part work

Large passes (this audit, feature work spanning several files) are broken
into one commit per logical batch/phase, each version-bumped
(`composer.json`'s `version` field + the sidebar-footer version string in
both `layouts/admin.blade.php` and `layouts/staff.blade.php`, 4th segment
+1), with a full `php artisan test` run — and, since this repo added
tooling for it, a `phpstan analyse` run — before every commit. `git status
--short` gets re-checked per-file before staging: a single invalid pathspec
in a multi-path `git add` silently drops every other path from staging too.
