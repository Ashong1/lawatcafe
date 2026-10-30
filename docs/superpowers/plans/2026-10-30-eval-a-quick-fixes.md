# Evaluation Fixes A — Quick Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the small, independent evaluator requests (items 1, 11, 17, 18, 19, 20, 21, 22 of the spec).

**Architecture:** Each task is a local change to an existing controller/view/service plus one feature test. No new tables. One shared piece: a `data-confirm` form attribute handled once in both layouts.

**Tech Stack:** Laravel 11, Blade + Alpine, SweetAlert2 (`window.confirmAction`), PHPUnit (sqlite in-memory), blade-lucide-icons.

**Spec:** `docs/capstone/EVALUATION_2026-10-28.md`

## Global Constraints

- Run artisan/phpunit as www-data: `sudo -u www-data php artisan test` (root-owned cache files caused an outage).
- `php artisan config:clear && php artisan route:clear` before tests (cached config breaks sqlite isolation).
- Every commit bumps BUILD in `composer.json` `version` (only place it lives); MINOR bump for the first commit of this batch → `1.29.0.218`, then `.219`, … ; add a CHANGELOG entry.
- Plain owner language in UI copy; no code names or snake_case on screen.
- SweetAlert2 for all confirmations; no in-page alert blocks.
- Never call OPNsense-mutating endpoints while testing; tests fake HTTP.

## Review Focus

- A form with `data-confirm` that also has `@submit="saving = true"` — the spinner must not stick on Cancel. Test: the handler calls `preventDefault()` before Alpine's flag matters; manual check on Ingredients edit.
- Voucher lockout must count only wrong codes, never a correct one, and must clear after a success. Test in Task 3.
- Wastage cap when `capacity_per_pack` is null (ingredient without packaging): fall back to `current_stock` only. Test in Task 4.
- Hourly backup while the server is off: nothing to do, but the "last backup" shown on Settings → Security must stay truthful (it reads files, unchanged).
- OpenRouter unreachable / no key: the usage card must say "Couldn't reach OpenRouter" and the page must still load. Test in Task 6.

---

### Task 1: Sign-in locks after 3 wrong tries (item 19)

**Files:**
- Modify: `app/Http/Requests/Auth/LoginRequest.php:16` — `MAX_ATTEMPTS = 3`
- Test: `tests/Feature/Auth/AuthenticationTest.php`

- [ ] Update `test_a_wrong_password_says_how_many_attempts_are_left`: first wrong → `' 2 attempts left.'`; after one more → ends with `'1 attempt left.'`.
- [ ] Update `test_the_last_wrong_attempt_locks_sign_in_with_a_countdown`: loop `range(1, 3)`.
- [ ] Run `sudo -u www-data php artisan test --filter=AuthenticationTest` → FAIL, change constant, → PASS.

### Task 2: Shared confirmation for inventory and Wi-Fi saves (item 1)

**Files:**
- Modify: `resources/views/layouts/admin.blade.php`, `resources/views/layouts/staff.blade.php` (next to `window.confirmAction`)
- Modify forms (add `data-confirm="<question>"`): `resources/views/inventory/ingredients.blade.php` (add/edit/restock), `inventory/products.blade.php` (add/edit), `inventory/wastage.blade.php`, `admin/settings/network.blade.php` (or whichever view `SettingController::network` returns), `network/traffic.blade.php` plan-speed form.
- Test: `tests/Feature/ConfirmBeforeSaveTest.php`

**Interfaces:** Produces the `data-confirm` attribute (value = dialog title; optional `data-confirm-text` = body).

- [ ] Write test: as admin, GET each page above and `assertSee('data-confirm=', false)`; GET `/dashboard` and `assertSee('form[data-confirm]', false)` (handler present in layout).
- [ ] Run → FAIL.
- [ ] Add a capture-phase `submit` listener on `document`: if `e.target.matches('form[data-confirm]')` and `!e.target.dataset.confirmed`, `preventDefault()` + `stopImmediatePropagation()`, call `confirmAction({title, text, icon:'question', confirmText:'Yes, save', callback: () => { form.dataset.confirmed = 1; form.requestSubmit(); }})`. Capture phase so Alpine `@submit` spinners don't start on Cancel.
- [ ] Add the attributes. Run → PASS. Commit.

### Task 3: Wrong Wi-Fi code 3 times → wait 5 minutes (item 17)

**Files:**
- Modify: `app/Http/Controllers/CaptivePortalController.php` `authenticate()` (~line 530)
- Test: `tests/Feature/PortalCodeLockoutTest.php`

- [ ] Tests: (a) 3 wrong codes from one IP, then the 4th attempt **with a valid code** is refused with a message containing `'Too many wrong codes'` and the voucher is not redeemed; (b) 2 wrong then a valid code succeeds and the counter is cleared (`RateLimiter::attempts('portal-code-fail:'.$ip) === 0`); (c) after `travel(301)->seconds()` the valid code works.
- [ ] Implement: key `'portal-code-fail:'.$request->ip()`; check `RateLimiter::tooManyAttempts($key, 3)` before the lookup → redirect with `__('Too many wrong codes. Please wait :min minutes or ask the staff for help.', ['min' => ceil(availableIn/60)])`; inside `$failed` call `RateLimiter::hit($key, 300)`; on success `RateLimiter::clear($key)`. Keep the existing `throttle:voucher-auth` route limit.
- [ ] Add the Filipino string to `lang/fil.json` (portal is EN/FIL).
- [ ] PASS, commit.

### Task 4: Wastage can't exceed one opened package or the stock (item 11)

**Files:**
- Modify: `app/Http/Controllers/WastageController.php:23`
- Modify: `resources/views/inventory/wastage.blade.php` (show "max X ml" hint for the chosen ingredient via `max` attribute)
- Test: `tests/Feature/WastageLimitTest.php`

- [ ] Tests: ingredient `capacity_per_pack=1000, current_stock=5000` → 1200 rejected with error on `quantity` containing `'1,000'`; 1000 accepted. Ingredient `capacity_per_pack=null, current_stock=300` → 400 rejected, 300 accepted.
- [ ] Implement: after validating `ingredient_id`, compute `$max = min(current_stock, capacity_per_pack ?: INF)`; validate `quantity` `max:$max` with message "One opened :pack holds :max :unit — log each package separately." (or "Only :max :unit in stock." when stock is the limit).
- [ ] PASS, commit.

### Task 5: "Archive" instead of "Remove" for accounts (item 18)

**Files:**
- Modify: `resources/views/accounts/index.blade.php` (button title/aria, dialog title "Archive X?", confirm "Yes, archive", section heading "Archived (n)", archive icon `x-lucide-archive`)
- Modify: `app/Http/Controllers/AccountController.php` flash messages lines 67, 130, 136 ("Archived …", "under \"Archived\"")
- Test: `tests/Feature/AccountManagementTest.php` (or the existing accounts test file — grep `accounts.destroy`)

- [ ] Update existing assertions on "Removed" text to "Archived"; add one asserting index shows `'Archived ('`.
- [ ] Change copy. ActivityLog action key `account_removed` stays (stored data); only its display label changes if one exists in the Activity Log view. PASS, commit.

### Task 6: AI pending-actions icon + OpenRouter usage card (items 20, 22)

**Files:**
- Modify: `resources/views/components/agent-pending-badge.blade.php:17` — `x-lucide-bot` with a small `x-lucide-cog` overlay (absolute, bottom-right, w-3 h-3); `title="AI actions waiting for approval"`.
- Modify: `app/Services/AiBudget.php` — add `account(): ?array`
- Modify: `app/Http/Controllers/Admin/SettingController.php::aiProviders` and `resources/views/admin/settings/ai-providers.blade.php` (card at top)
- Test: `tests/Feature/OpenRouterUsageCardTest.php`

**Interfaces:** `AiBudget::account(): ?array{balance: float, credits: float, used: float, free_used: int, free_limit: int, free_remaining: int}` — null when no key or OpenRouter unreachable. Cached 300 s under `openrouter_account`.

- [ ] Tests (Http::fake for `openrouter.ai/api/v1/key` and `/credits`): page shows `'₱'`-free dollar balance `'-$0.20'` and `'13 of 50'` for the live-like payload (`total_credits 5`, `total_usage 5.2017`, `free_model_daily_requests used 13 limit 50`); negative balance shows `'below zero'` warning; `Http::fake` 500 → page 200 and shows `"Couldn't reach OpenRouter"`. Note `tests/TestCase.php` binds an offline AiBudget — override the binding in this test.
- [ ] Implement `account()` with `Http::withToken($key)->timeout(8)` for both endpoints; card copy: "AI credit balance", "Free AI requests today", "Resets 8:00 AM".
- [ ] PASS, commit.

### Task 7: Hourly backups while open, keep a week (item 21)

**Files:**
- Modify: `routes/console.php:59-60` — `->hourly()` instead of `->dailyAt('23:30')`
- Modify: `app/Services/BackupService.php:21` — `KEEP = 168`
- Modify: `app/Console/Commands/BackupDatabase.php` description; Settings → Security view text that says "nightly"/"11:30 PM" (grep `11:30\|nightly` in `resources/views`)
- Test: `tests/Feature/BackupScheduleTest.php`

- [ ] Test: `Schedule` events contain `backup:database` with expression `'0 * * * *'`; `BackupService::KEEP === 168`.
- [ ] Implement, update copy to "every hour while the server is on; the last 7 days are kept". PASS, commit.

### Task 8: Verify and ship

- [ ] `sudo -u www-data php artisan test` — full suite green (not just filters).
- [ ] `sudo -u www-data php artisan optimize` ; `npm run build && chown -R www-data:www-data public/build` if any view uses new Tailwind classes.
- [ ] Mark items 1, 11, 17–22 Done in `docs/capstone/EVALUATION_2026-10-28.md`; push to origin/main.
