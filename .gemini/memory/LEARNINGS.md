# Project Learnings & Retrospectives

### 2026-07-26 - AI Agent Tool-Calling System (POS + Network merge, per capstone thesis)
- **Action**: Extended `AIService` from a pure prompt-in/text-out chatbot into a real tool-calling agent, with a full permission/audit/isolation architecture around it.
- **Goal**: Move the system's AI from "answers questions about the business" to "can take real actions across both POS and network domains," which is the actual thesis of the capstone.
- **Outcome**:
  - Extracted business logic out of controllers into `app/Services/{Voucher,Blocklist,Ingredient,Sale,SupplierOrder}Service.php` so both HTTP routes and AI tools call the same code path.
  - Added `app/Services/Agent/*`: `ToolRegistry` (hardcoded per-audience allowlists — guest/staff/admin), `ToolCallOrchestrator` (multi-turn tool-call loop with a two-layer isolation backstop), `PermissionResolver` (auto/confirm/admin_only tiers, with role as a hard ceiling AND tool-default `admin_only` as an un-loosenable floor), `AuditLogger`, `CrossDomainCorrelationService` (deterministic POS+network signal detection).
  - `AIService` now normalizes tool-calling across all 3 providers (Gemini's native `functionDeclarations`/`functionCall` vs. Groq/OpenRouter's OpenAI-compatible `tools`/`tool_calls`) into one canonical shape, and gained a fast-path (interactive chat, tighter timeouts/fewer models) vs. deep-path (forecasting/scheduled analysis) split.
  - New `ai_action_audits` and `purchase_order_drafts` tables; new `agent:analyze` scheduled command (hourly) that reuses the exact same permission/audit pipeline as interactive chat — a scheduled `admin_only` proposal still requires human confirmation, no bypass for being non-interactive.
  - 30 new feature tests under `tests/Feature/Agent/*` plus service-parity tests; full suite (58 tests) green.
- **Rule**: When a tool's own hardcoded default tier is the strictest available (`admin_only`), do not let a Setting-based override loosen it for anyone, even admins reconfiguring it live — the role-ceiling alone isn't enough, because it only blocks the *lowest* tier (auto), not intermediate ones (confirm). Discovered this gap while writing `PermissionResolverTest` for `blockDevice`, before it shipped.
- **Rule**: Guest-facing AI tool exposure must be a hardcoded allowlist in code (`ToolRegistry`), never `Setting`-driven — admin-editable config is the wrong place to anchor a security boundary that guests' prompt injection could target.

### 2026-05-20 - Modernizing UI: Text to Icons Transition
- **Action**: Replaced text-based action buttons (Edit, Delete, Save, etc.) with Lucide icons across all management views.
- **Goal**: Improve UI cleanliness and adhere to modern design standards.
- **Outcome**: 
  - Standardized the use of `x-lucide-pencil` for editing and `x-lucide-trash-2` for deletion.
  - Added `hover:bg-amber-100` and `hover:bg-red-50` for better interactive feedback on icon buttons.
  - Improved accessibility by adding `title` attributes to icon-only buttons.
- **Rule**: Prefer icons for primary table actions (Edit/Delete) to maximize horizontal space and reduce visual clutter. Always provide a `title` attribute for screen readers and tooltips.

### 2026-05-20 - System-Wide Audit and Critical Fixes
- **Action**: Performed full audit using `codebase_investigator`, fixing critical issues.
- **Goal**: Resolve missing database migrations, backend validation flaws, and frontend UX bugs.
- **Outcome**: 
  - Restored missing `create_shifts_table`, `create_sale_items_table`, and `create_categories_table` migrations.
  - Secured `PosController@checkout` with server-side calculation and pre-deduction inventory validation.
  - Fixed mass-assignment vulnerabilities in `Sale` and `Voucher` models.
  - Replaced native `prompt()` in the POS UI with an Alpine.js modal for closing shifts.
- **Rule**: Never trust client-side prices in POS systems. Always recalculate totals server-side based on database records before processing transactions.

### 2026-05-23 - Enhanced System Monitoring: CPU Temperature
- **Action**: Integrated CPU temperature tracking into the Admin Dashboard's System Health section.
- **Goal**: Provide real-time hardware health visibility for café servers (especially Raspberry Pi or local Linux hosts).
- **Outcome**: 
  - Added logic to `DashboardController` to read `/sys/class/thermal/thermal_zone0/temp`.
  - Implemented a progress bar visualization in the dashboard UI.
  - Added robust fallbacks for non-Linux environments to prevent dashboard crashes.
- **Rule**: When adding system-level metrics, always provide fallbacks and use cached values (30s-60s) to minimize server load. Avoid executing complex shell commands on every page load.

### 2026-05-23 - Captive Portal UX & Compliance
- **Action**: Upgraded the Captive Portal with a split-screen desktop layout, active state styling, and legal compliance checkboxes.
- **Goal**: Improve desktop UX while maintaining mobile responsiveness, and ensure network compliance.
- **Outcome**: 
  - Implemented a `hidden lg:flex` split-screen layout that provides a premium desktop experience without affecting mobile users.
  - Standardized the "Intermediate Unlock" flow (`unlock.blade.php`) to ensure OS-level captive portal detection is correctly handled for Android/iOS devices.
  - Added mandatory "Terms of Service" checkboxes to both voucher and payment forms to meet network administration standards.
- **Rule**: Captive portals should always include an "unlocking" phase that performs a client-side form POST to the gateway IP. This ensures the device's OS recognizes the portal login as successful and dismisses the "Log in to network" notification.

### 2026-07-26 - Antigravity CLI Workspace Custom Agent Migration
- **Action**: Migrated workspace custom agent definition (`architect-fixer`) to `.agents/agents/architect-fixer.md`.
- **Goal**: Ensure custom subagents are automatically detected by Antigravity CLI for `@agent` routing and `agy --agent` commands.
- **Outcome**: 
  - Placed workspace custom agent configurations under `.agents/agents/<agent-name>.md`.
  - Updated agent frontmatter and instructions to maintain backward compatibility with `GEMINI.md` alongside standard `AGENTS.md`.
- **Rule**: Store workspace custom agents in `.agents/agents/<name>.md`. Always maintain dual context references (`AGENTS.md` and `GEMINI.md`) in agent system prompts to preserve context integrity across CLI environments.

### 2026-07-26 - Strict Database Safety & Preservation Policy
- **Action**: Established explicit system-wide mandates against destructive database actions.
- **Goal**: Prevent data loss in development and production environments across all agent workflows.
- **Outcome**: 
  - Mandated that commands such as `php artisan migrate:fresh`, `db:wipe`, `migrate:reset`, `DROP DATABASE`, or `TRUNCATE` are strictly prohibited.
  - Required all schema alterations to be safe, additive migrations, using isolated test transactions or separate test databases for test execution.
- **Rule**: Never run destructive database operations. Use non-destructive, additive migrations and database transactions for testing to protect data integrity.

### 2026-07-26 - Favicon & Page Title Standardization
- **Action**: Standardized browser tab branding, favicons (SVG, PNG, ICO), and page titles across layout views.
- **Goal**: Replace default Laravel branding with Lawa't Kape logo and brand name across all layouts (admin, staff, guest, app, portal).
- **Outcome**: 
  - Generated `public/favicon.svg`, `public/favicon.png`, and `public/favicon.ico`.
  - Added cache-busting versioned favicon `<link>` tags (`?v=1`) across all layout templates.
  - Set `APP_NAME="Lawa't Kape"` in `.env`.
- **Rule**: Always include cache-busting parameters (`?v=1`) on favicon links across all layout templates to prevent aggressive browser caching issues.

### 2026-07-26 - Password Reset Diagnostics & CLI Management
- **Action**: Diagnosed "Forgot Password not working" issue and created direct CLI password reset tooling.
- **Root Causes**: 
  1. The `users` database table was empty, causing `Password::sendResetLink()` to return `passwords.user` ("We can't find a user with that email address").
  2. Local environment uses `MAIL_MAILER=log`, which writes emails to logs rather than sending SMTP emails.
- **Outcome**: 
  - Seeded default accounts safely without wiping database data.
  - Created `app/Console/Commands/ResetUserPassword.php` (`php artisan user:reset-password {email} {password}`) for CLI password management.
  - Verified all 4 feature tests in `PasswordResetTest` pass.
- **Rule**: Before troubleshooting auth flows, verify database user existence and active `MAIL_MAILER` transport driver in `.env`.

### 2026-07-26 - Test Transaction Isolation & Data Preservation
- **Action**: Switched feature test suits from `RefreshDatabase` to `DatabaseTransactions`.
- **Goal**: Prevent automated tests from truncating or resetting application development database tables.
- **Outcome**: Feature tests run safely inside database transactions and roll back without wiping user records.
- **Rule**: Use `DatabaseTransactions` for feature tests running against shared databases to maintain strict data preservation.

### 2026-07-26 - Removal of Gmail IMAP Receipt Audit
- **Action**: Completely removed the Gmail IMAP audit scheduled task, console command, controller parameters, settings UI, and composer package.
- **Goal**: Clean up unused code and dependencies since GCash no longer dispatches transaction receipts via email.
- **Outcome**: 
  - Uninstalled `webklex/laravel-imap` package via Composer.
  - Deleted `app/Console/Commands/ScanImapReceipts.php` and removed `imap:scan-receipts` from `routes/console.php`.
  - Cleaned up `SettingController` and `admin.settings.integrations` view while preserving `AIService` image parsing for portal upload receipts.
  - Removed obsolete `imap_*` rows from the `settings` database table.
- **Rule**: When removing deprecated third-party integrations, clean up scheduled console commands, controller validation, UI settings views, database keys, and composer dependencies to maintain a lean application footprint.

### 2026-07-28 - Eliminating Paint-Holding Blank Page (~530ms TTFB) & Sidebar Width Dips
- **Action**: Diagnosed and resolved page paint-holding timeout (~500ms blank page at t=27.13–27.60) and 220px sidebar width dips during navigation.
- **Root Causes**:
  1. Synchronous OPNsense API calls (`listSessions()`, `getArpTable()`, `getGatewayStatus()`, `getInterfaceStats()`) running during page render without tight timeouts and short cache TTLs (5s). Under high network latency or cold cache, request latency exceeded Chrome's ~500ms paint-holding budget, causing Chrome to dump the previous DOM and render an empty document (`#FDF8F5` background).
  2. The sidebar `<aside>` element relied solely on Alpine.js reactive class binding (`:class="sidebarOpen ? 'w-64' : 'w-20'"`) without having `w-64 flex-none` in its static HTML `class="..."` attribute. During cross-document View Transitions, the un-hydrated HTML snapshot of the incoming page was parsed before JS execution, momentarily rendering at content auto-width (~220px) before expanding to 256px (`w-64`).
- **Outcome**:
  - Reduced OPNsense HTTP client connect timeout to 1s and total timeout to 2s in `OpnSenseService.php`, ensuring failure/fallback occurs fast without hanging web requests.
  - Increased OPNsense cache TTLs to 15s with stale-cache fallback (`Cache::get(...)`) when API requests fail or time out.
  - Reduced `/network/sessions` server render time (TTFB) from ~400ms-600ms down to **~18ms** (warm) / **~99ms** (cold).
  - Added `w-64 flex-none` directly to static `class="..."` on `<aside>` in both `admin.blade.php` and `staff.blade.php` to eliminate the 220px initial-render width dip during View Transitions.
  - Added `html { scrollbar-gutter: stable; }` to `resources/css/app.css` to prevent layout jumps when vertical scrollbars toggle.
- **Rule**: Never allow un-bounded or long-timeout external API calls in HTTP request handling paths. Always set explicit HTTP connect & total timeouts (1s-2s max) and use cached fallbacks to protect TTFB against browser paint-holding limits (~500ms). Always include default static size classes on view-transition elements so raw HTML snapshots render with correct layout bounds before JS hydration.

### 2026-07-30 - Captive Portal Vouchers Silently Rejected by the 1s/2s OPNsense Timeout
- **Action**: Diagnosed customer reports that generated Wi-Fi vouchers "wouldn't be accepted" at the captive portal.
- **Root Cause**: `OpnSenseService::authorizeDevice()` — the call that redeems a voucher via `captiveportal/session/connect` — shares `client()`'s global 1s/2s budget from the 2026-07-28 entry above. That budget is correct for the cached, stale-fallback render/poll methods it was designed for, but `authorizeDevice()` is a one-shot write with **no fallback**: a timeout there isn't stale data, it's an outright rejected voucher. Production logs show this exact router occasionally taking well over 2s (even 10s+, and one SSL handshake failure) to respond, so real customers were hitting this.
- **Outcome**: `client()` now takes optional `$connectTimeout`/`$timeoutSeconds` params (still defaulting to 1s/2s for every existing render-path caller). `authorizeDevice()` explicitly requests `client(4, 8)` instead.
- **Rule**: A shared low-timeout HTTP client tuned for a cached/fallback read path is wrong for a write with no fallback. Before applying a render-path timeout rule to a new OPNsense call, check whether that call has a cached value to fall back to — if not (auth, disconnect, any mutation), it needs its own longer, explicit budget rather than inheriting the render-path default.







